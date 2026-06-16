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
            $this->error("User not found: " . self::ORELVYS_EMAIL);
            return Command::FAILURE;
        }

        if (! $bert) {
            $this->error("User not found: " . self::BERT_EMAIL);
            return Command::FAILURE;
        }

        $this->line("Orelvys : [{$orelvys->id}] {$orelvys->name} ({$orelvys->email})");
        $this->line("Bert    : [{$bert->id}] {$bert->name} ({$bert->email})");
        $this->newLine();

        // --- Step 2: count candidates ---
        $total = DB::table('safety_questions')->count();

        $createdCandidates = DB::table('safety_questions')
            ->whereNull('created_by_user_id')
            ->count();

        $updatedCandidates = DB::table('safety_questions')
            ->whereBetween('updated_at', [self::SUNDAY_UTC_START, self::SUNDAY_UTC_END])
            ->whereNull('updated_by_user_id')
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
                ['Total questions',                                                  $total],
                ['→ set created_by_user_id = Orelvys (currently null)',             $createdCandidates],
                ['→ set updated_by_user_id = Bert (modified 2026-06-14, null)',     $updatedCandidates],
                ['Skip: created_by_user_id already set',                            $alreadyCreated],
                ['Skip: updated_by_user_id already set',                            $alreadyUpdated],
            ]
        );

        if ($isDryRun) {
            $this->warn('DRY-RUN — no changes applied. Add --apply to execute.');
            return Command::SUCCESS;
        }

        // --- Step 3: apply (DB query builder; does NOT touch updated_at) ---
        $this->info('Applying backfill...');

        $appliedCreated = DB::table('safety_questions')
            ->whereNull('created_by_user_id')
            ->update(['created_by_user_id' => $orelvys->id]);

        $appliedUpdated = DB::table('safety_questions')
            ->whereBetween('updated_at', [self::SUNDAY_UTC_START, self::SUNDAY_UTC_END])
            ->whereNull('updated_by_user_id')
            ->update(['updated_by_user_id' => $bert->id]);

        $this->info("created_by_user_id set on {$appliedCreated} question(s).");
        $this->info("updated_by_user_id set on {$appliedUpdated} question(s).");
        $this->info('Done.');

        return Command::SUCCESS;
    }
}
