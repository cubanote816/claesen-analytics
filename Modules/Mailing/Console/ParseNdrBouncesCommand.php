<?php

namespace Modules\Mailing\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Mailing\Enums\BounceClassification;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Enums\SuppressionReason;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\MessageEvent;
use Modules\Mailing\Services\BounceParserService;
use Modules\Mailing\Services\MicrosoftGraphService;
use Modules\Mailing\Services\SuppressionService;

/**
 * Reads unread NDR messages from the dedicated bounce inbox and updates
 * the suppression list accordingly.
 *
 * Soft bounces are counted before suppression:
 *   - SOFT event recorded every time.
 *   - Suppression applied only when count >= config('mailing.bounce_soft_limit').
 *
 * Correlation with mailing_messages is best-effort by email address (most recent sent message).
 * MAI-029 tracks adding X-Mailing-Token to outgoing emails for exact correlation.
 */
class ParseNdrBouncesCommand extends Command
{
    protected $signature = 'mailing:parse-bounces
                            {--dry-run : Log proposed actions without writing to DB or marking messages read}
                            {--batch=50 : Number of unread NDR messages to process per run}';

    protected $description = 'Parse NDR bounce messages from the dedicated bounce inbox and update suppression list.';

    public function handle(
        MicrosoftGraphService $graph,
        BounceParserService   $parser,
        SuppressionService    $suppression,
    ): int {
        $mailbox   = config('mailing.ndr_inbox');
        $batch     = (int) ($this->option('batch') ?? config('mailing.ndr_batch_size', 50));
        $dryRun    = (bool) $this->option('dry-run');
        $softLimit = (int) config('mailing.bounce_soft_limit', 3);

        if ($dryRun) {
            $this->info('[dry-run] No writes will be performed.');
        }

        $this->info("Fetching up to {$batch} unread NDR messages from {$mailbox}...");

        try {
            $messages = $graph->fetchUnreadMessages($mailbox, $batch);
        } catch (\RuntimeException $e) {
            Log::error('mailing:parse-bounces — Graph fetch failed.', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (empty($messages)) {
            $this->info('No unread NDR messages found.');
            return self::SUCCESS;
        }

        $this->info('Found ' . count($messages) . ' message(s) to process.');

        $stats = ['hard' => 0, 'soft' => 0, 'suppressed_soft' => 0, 'unknown' => 0, 'skipped' => 0];

        foreach ($messages as $message) {
            $messageId = $message['id'];
            $subject   = $message['subject'] ?? '';
            $body      = $message['body']['content'] ?? '';

            $email = $parser->extractEmail($subject, $body);

            if (! $email) {
                $this->warn("  [SKIP] Could not extract email — Graph ID: {$messageId}");
                Log::warning('mailing:parse-bounces: unrecognized NDR format', [
                    'graph_message_id' => $messageId,
                    'subject'          => $subject,
                ]);
                $stats['skipped']++;
                if (! $dryRun) {
                    $graph->markMessageRead($mailbox, $messageId);
                }
                continue;
            }

            $classification = $parser->classifyBounce($body);

            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry-run] email=%s  classification=%s  action=%s  graph_id=%s',
                    $email,
                    $classification->name,
                    $this->proposedAction($classification, $email, $softLimit),
                    $messageId,
                ));
                continue;
            }

            $campaignMessage = $this->findLatestMessage($email);

            match ($classification) {
                BounceClassification::HARD    => $this->handleHard($email, $campaignMessage, $suppression, $stats),
                BounceClassification::SOFT    => $this->handleSoft($email, $campaignMessage, $suppression, $softLimit, $stats),
                BounceClassification::UNKNOWN => $this->handleUnknown($email, $messageId, $stats),
            };

            $graph->markMessageRead($mailbox, $messageId);
        }

        if (! $dryRun) {
            $this->table(
                ['Hard', 'Soft events', 'Suppressed (soft limit)', 'Unknown', 'Skipped'],
                [[$stats['hard'], $stats['soft'], $stats['suppressed_soft'], $stats['unknown'], $stats['skipped']]],
            );
            Log::info('mailing:parse-bounces completed', $stats);
        }

        return self::SUCCESS;
    }

    private function handleHard(
        string           $email,
        ?CampaignMessage $message,
        SuppressionService $suppression,
        array            &$stats,
    ): void {
        if ($message) {
            MessageEvent::create([
                'message_id'  => $message->id,
                'event_type'  => MessageEventType::BOUNCED_HARD,
                'occurred_at' => now(),
                'metadata'    => ['source' => 'ndr_parser'],
            ]);
        }

        try {
            $suppression->suppress(
                $email,
                SuppressionReason::HARD_BOUNCE,
                $message?->prospect_id,
                $message?->campaign_id,
            );
        } catch (\DomainException) {
            // Already permanently suppressed — no action needed.
        }

        $this->line("  [HARD] suppressed: {$email}");
        $stats['hard']++;
    }

    private function handleSoft(
        string           $email,
        ?CampaignMessage $message,
        SuppressionService $suppression,
        int              $softLimit,
        array            &$stats,
    ): void {
        if ($message) {
            MessageEvent::create([
                'message_id'  => $message->id,
                'event_type'  => MessageEventType::BOUNCED_SOFT,
                'occurred_at' => now(),
                'metadata'    => ['source' => 'ndr_parser'],
            ]);
        }

        $softCount = DB::table('mailing_message_events')
            ->join('mailing_messages', 'mailing_messages.id', '=', 'mailing_message_events.message_id')
            ->where('mailing_messages.email', $email)
            ->where('mailing_message_events.event_type', MessageEventType::BOUNCED_SOFT->value)
            ->count();

        $stats['soft']++;

        if ($softCount >= $softLimit) {
            try {
                $suppression->suppress(
                    $email,
                    SuppressionReason::SOFT_BOUNCE_LIMIT,
                    $message?->prospect_id,
                    $message?->campaign_id,
                    notes: "Auto-suppressed after {$softCount} soft bounces.",
                );
                $this->line("  [SOFT] suppressed (limit {$softLimit} reached, count={$softCount}): {$email}");
                $stats['suppressed_soft']++;
            } catch (\DomainException) {
                // Already permanently suppressed — no action needed.
            }
        } else {
            $this->line("  [SOFT] event recorded (count={$softCount}/{$softLimit}): {$email}");
        }
    }

    private function handleUnknown(string $email, string $messageId, array &$stats): void
    {
        $this->warn("  [UNKNOWN] classification failed — email={$email} graph_id={$messageId}");
        Log::warning('mailing:parse-bounces: unknown bounce classification', [
            'email'            => $email,
            'graph_message_id' => $messageId,
        ]);
        $stats['unknown']++;
    }

    private function findLatestMessage(string $email): ?CampaignMessage
    {
        return CampaignMessage::where('email', $email)
            ->where('status', 'sent')
            ->orderByDesc('sent_at')
            ->first();
    }

    private function proposedAction(BounceClassification $classification, string $email, int $softLimit): string
    {
        return match ($classification) {
            BounceClassification::HARD    => 'suppress(hard_bounce)',
            BounceClassification::SOFT    => "record_event + suppress if >= {$softLimit} soft bounces",
            BounceClassification::UNKNOWN => 'mark_read_only (no suppression)',
        };
    }
}
