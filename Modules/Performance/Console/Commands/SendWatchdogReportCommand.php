<?php

namespace Modules\Performance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Modules\Performance\Services\CashFlowWatchdogService;
use Modules\Performance\Emails\WatchdogRiskReportMail;

class SendWatchdogReportCommand extends Command
{
    protected $signature = 'performance:send-watchdog-report {email? : The recipient email address}';
    protected $description = 'Executes the CashFlow Watchdog and sends the Monday Morning Risk Report by email.';

    public function handle(CashFlowWatchdogService $service)
    {
        $email = $this->argument('email') ?? env('WATCHDOG_REPORT_EMAIL', 'gerencia@claesen.be');
        
        $this->info("Awaking AI Watchdog...");
        $report = $service->generateRiskReport();

        if (is_array($report) && isset($report['risky_projects'])) {
            $this->info("AI identified " . count($report['risky_projects']) . " critical WIP projects.");
        }
        
        $this->info("Sending Monday Morning Risk Report to: {$email}");
        Mail::to($email)->send(new WatchdogRiskReportMail($report));
        
        $this->info("Watchdog report successfully sent!");
    }
}
