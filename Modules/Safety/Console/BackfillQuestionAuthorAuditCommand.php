<?php

declare(strict_types=1);

namespace Modules\Safety\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillQuestionAuthorAuditCommand extends Command
{
    protected $signature = 'safety:backfill-question-authors
                            {--apply : Apply changes (default is dry-run)}';

    protected $description = 'Backfill created_by_user_id / updated_by_user_id on safety_questions for historical rows';

    // Author emails — resolved at runtime to avoid hardcoded IDs
    private const ORELVYS_EMAIL = 'orelvys.cuellar@claesen-verlichting.be';
    private const BERT_EMAIL    = 'bert.Bertels@claesen-verlichting.be';

    // Europe/Brussels 2026-06-14 expressed as UTC (UTC+2 in summer)
    private const SUNDAY_UTC_START = '2026-06-13 22:00:00';
    private const SUNDAY_UTC_END   = '2026-06-14 21:59:59';

    public function handle(): int
    {
        $isDryRun = ! $this->option('apply');

        // --- Step 1: resolve user identities ---
        $orelvys = DB::table('users')
            ->where('email', self::ORELVYS_EMAIL)
            ->first(['id', 'name', 'email']);

        $bert = DB::table('users')
            ->where('email', self::BERT_EMAIL)
            ->first(['id', 'name', 'email']);

        if (! $orelvys) {
            $this->error('User not found: ' . self::ORELVYS_EMAIL);
            return Command::FAILURE;
        }

        if (! $bert) {
            $this->error('User not found: ' . self::BERT_EMAIL);
            return Command::FAILURE;
        }

        $this->line("Orelvys : [{$orelvys->id}] {$orelvys->name} ({$orelvys->email})");
        $this->line("Bert    : [{$bert->id}] {$bert->name} ({$bert->email})");
        $this->newLine();

        // --- Step 2: count candidates ---
        $total = DB::table('safety_questions')->count();

        // created_by = Orelvys: questions created BEFORE 2026-06-14 (not in Sunday window)
        $createdByOrelvys = DB::table('safety_questions')
            ->whereNull('created_by_user_id')
            ->where(fn($q) => $q
                ->where('created_at', '<', self::SUNDAY_UTC_START)
                ->orWhere('created_at', '>', self::SUNDAY_UTC_END)
            )
            ->count();

        // created_by = Bert: questions created ON 2026-06-14 (in Sunday window)
        $createdByBert = DB::table('safety_questions')
            ->whereNull('created_by_user_id')
            ->whereBetween('created_at', [self::SUNDAY_UTC_START, self::SUNDAY_UTC_END])
            ->count();

        // updated_by = Bert: updated on 2026-06-14 AND created_at != updated_at (actually edited)
        $updatedByBert = DB::table('safety_questions')
            ->whereNull('updated_by_user_id')
            ->whereBetween('updated_at', [self::SUNDAY_UTC_START, self::SUNDAY_UTC_END])
            ->whereColumn('created_at', '!=', 'updated_at')
            ->count();

        // updated_by stays null: created_at = updated_at on 2026-06-14 (never edited after creation)
        $neverEdited = DB::table('safety_questions')
            ->whereBetween('updated_at', [self::SUNDAY_UTC_START, self::SUNDAY_UTC_END])
            ->whereColumn('created_at', 'updated_at')
            ->count();

        $alreadyCreated = DB::table('safety_questions')
            ->whereNotNull('created_by_user_id')
            ->count();

        $alreadyUpdated = DB::table('safety_questions')
            ->whereNotNull('updated_by_user_id')
            ->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total questions',                                                              $total],
                ['→ set created_by = Orelvys  (created before 2026-06-14, null)',               $createdByOrelvys],
                ['→ set created_by = Bert     (created on 2026-06-14, null)',                   $createdByBert],
                ['→ set updated_by = Bert     (modified 2026-06-14, created≠updated, null)',    $updatedByBert],
                ['  skip updated_by = null    (created=updated on 2026-06-14, never edited)',   $neverEdited],
                ['Skip: created_by_user_id already set',                                        $alreadyCreated],
                ['Skip: updated_by_user_id already set',                                        $alreadyUpdated],
            ]
        );

        if ($isDryRun) {
            $this->warn('DRY-RUN — no changes applied. Add --apply to execute.');
            return Command::SUCCESS;
        }

        // --- Step 3: apply (DB query builder; does NOT touch updated_at) ---
        $this->info('Applying backfill...');

        $appliedCreatedOrelvys = DB::table('safety_questions')
            ->whereNull('created_by_user_id')
            ->where(fn($q) => $q
                ->where('created_at', '<', self::SUNDAY_UTC_START)
                ->orWhere('created_at', '>', self::SUNDAY_UTC_END)
            )
            ->update(['created_by_user_id' => $orelvys->id]);

        $appliedCreatedBert = DB::table('safety_questions')
            ->whereNull('created_by_user_id')
            ->whereBetween('created_at', [self::SUNDAY_UTC_START, self::SUNDAY_UTC_END])
            ->update(['created_by_user_id' => $bert->id]);

        $appliedUpdatedBert = DB::table('safety_questions')
            ->whereNull('updated_by_user_id')
            ->whereBetween('updated_at', [self::SUNDAY_UTC_START, self::SUNDAY_UTC_END])
            ->whereColumn('created_at', '!=', 'updated_at')
            ->update(['updated_by_user_id' => $bert->id]);

        $this->info("created_by_user_id = Orelvys set on {$appliedCreatedOrelvys} question(s).");
        $this->info("created_by_user_id = Bert    set on {$appliedCreatedBert} question(s).");
        $this->info("updated_by_user_id = Bert    set on {$appliedUpdatedBert} question(s).");
        $this->info('Done.');

        return Command::SUCCESS;
    }
}
