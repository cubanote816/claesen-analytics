<?php

namespace Modules\Performance\Console\Commands;

use Illuminate\Console\Command;
use Modules\Cafca\Models\Employee;
use Modules\Performance\Services\TechnicianAnalysisService;

class AnalyzeTechniciansCommand extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'performance:analyze-technicians {--force : Re-analyze even if already present} {--limit= : Limit the number of technicians to process}';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Run AI analysis for all active technicians and persist to database';

    /**
     * Execute the console command.
     */
    public function handle(TechnicianAnalysisService $service): void
    {
        $limit = $this->option('limit');
        $force = $this->option('force');

        $query = Employee::where('fl_active', 1);

        if (!$force) {
            $query->whereDoesntHave('insight');
        }

        if ($limit) {
            $query->limit((int)$limit);
        }

        $employees = $query->get();

        if ($employees->isEmpty()) {
            $this->info('Geen actieve technici gevonden voor analyse.');
            return;
        }

        $this->info("Analyse gestart voor {$employees->count()} technici...");

        $bar = $this->output->createProgressBar($employees->count());
        $bar->start();

        foreach ($employees as $index => $employee) {
            try {
                $service->analyzeTechnician((string)$employee->id, $employee->name);
                
                // Rate limiting to stay within Gemini Flash Free tier (approx 15 requests/min)
                // We sleep 4 seconds between requests to be safe.
                if ($index < $employees->count() - 1) {
                    sleep(4);
                }
            } catch (\Exception $e) {
                $this->error("\nFout bij analyseren van {$employee->name}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('IA-analyse succesvol voltooid!');
    }
}
