<?php

namespace Modules\Performance\Tests\Feature;

use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Modules\Performance\Console\Commands\PopulateProjectInsightsCommand;
use Modules\Performance\Emails\ImmediateRiskAlertMail;
use Modules\Performance\Emails\WatchdogRiskReportMail;
use Modules\Performance\Models\ProjectInsight;
use Modules\Performance\Services\CashFlowWatchdogService;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * CLA-532: Watchdog recipients / thresholds come from config('performance.watchdog.*')
 * (config:cache-safe). A missing recipient must never produce a false success:
 * the command fails without running the service, the alert paths warn and send
 * nothing, and no outbound HTTP happens.
 *
 * DB-backed (RefreshDatabase). Authored here; executed in CI (no local MySQL).
 */
class WatchdogRecipientConfigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
    }

    // ─── performance:send-watchdog-report ────────────────────────────────────

    public function test_send_watchdog_report_fails_without_a_recipient_and_never_runs_the_service(): void
    {
        config(['performance.watchdog.report_email' => null]);

        $service = Mockery::mock(CashFlowWatchdogService::class);
        $service->shouldNotReceive('generateRiskReport');
        $this->app->instance(CashFlowWatchdogService::class, $service);

        $this->artisan('performance:send-watchdog-report')
            ->expectsOutputToContain('No recipient configured')
            ->assertExitCode(1);

        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_send_watchdog_report_runs_the_service_once_and_mails_the_configured_recipient(): void
    {
        config(['performance.watchdog.report_email' => 'boss@example.test']);

        $service = Mockery::mock(CashFlowWatchdogService::class);
        $service->shouldReceive('generateRiskReport')->once()->andReturn(['risky_projects' => []]);
        $this->app->instance(CashFlowWatchdogService::class, $service);

        $this->artisan('performance:send-watchdog-report')->assertExitCode(0);

        Mail::assertSent(
            WatchdogRiskReportMail::class,
            fn ($mail) => $mail->hasTo('boss@example.test'),
        );
        Http::assertNothingSent();
    }

    // ─── Vanguard alerts (CashFlowWatchdogService) ──────────────────────────

    public function test_vanguard_alerts_warn_and_send_nothing_without_a_recipient(): void
    {
        config(['performance.watchdog.vanguard_email' => null]);
        Log::spy();

        $alerts = new Collection([
            ['id' => 'P-1', 'name' => 'Test Project', 'wip' => 30000],
        ]);

        $method = new ReflectionMethod(CashFlowWatchdogService::class, 'triggerVanguardAlerts');
        $method->setAccessible(true);
        $method->invoke(app(CashFlowWatchdogService::class), $alerts);

        Mail::assertNothingSent();
        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($msg) => str_contains((string) $msg, 'no recipient configured'))
            ->atLeast()->once();
    }

    // ─── Immediate high-risk alert (extracted from the ETL loop) ─────────────

    public function test_immediate_alert_without_a_recipient_neither_sends_nor_sets_the_timestamp(): void
    {
        config(['performance.watchdog.report_email' => null]);

        $insight = $this->highRiskInsight();
        $this->invokeSendImmediateAlert($insight, 30000.0, $insight->project_id);

        Mail::assertNothingSent();
        Http::assertNothingSent();
        $this->assertNull($insight->fresh()->last_immediate_alert_at);
    }

    public function test_immediate_alert_with_a_recipient_sends_it_and_sets_the_timestamp(): void
    {
        config(['performance.watchdog.report_email' => 'ops@example.test']);

        $insight = $this->highRiskInsight();
        $this->invokeSendImmediateAlert($insight, 30000.0, $insight->project_id);

        Mail::assertSent(
            ImmediateRiskAlertMail::class,
            fn ($mail) => $mail->hasTo('ops@example.test'),
        );
        $this->assertNotNull($insight->fresh()->last_immediate_alert_at);
    }

    // ─── helpers ───────────────────────────────────────────────────────────

    private function highRiskInsight(): ProjectInsight
    {
        return ProjectInsight::create([
            'project_id' => 'PRJ-'.uniqid(),
            'insight_type' => 'audit_budget',
            'efficiency_score' => 20.0,
            'critical_leak' => 'Costs exceed billing.',
            'last_data_hash' => md5('x'),
            'last_audited_at' => now(),
            'last_immediate_alert_at' => null,
        ]);
    }

    private function invokeSendImmediateAlert(ProjectInsight $insight, float $wip, string $projectId): void
    {
        $command = $this->app->make(PopulateProjectInsightsCommand::class);
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

        $method = new ReflectionMethod($command, 'sendImmediateAlert');
        $method->setAccessible(true);
        $method->invoke($command, $insight, $wip, $projectId);
    }
}
