<?php

namespace Modules\Performance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Modules\Intelligence\Services\GeminiService;
use Modules\Performance\Emails\VanguardImmediateAlertMail;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorLabor;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorRelation;
use Modules\Performance\Services\CashFlowWatchdogService;
use Modules\Performance\Services\EmployeePerformanceService;
use Modules\Performance\Services\TechnicianAnalysisService;
use Tests\TestCase;

/**
 * CLA-688 (S2) — project name = `descr`, client = mirror relation.
 */
class ProjectDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    private const PROJECT_NAME = 'Limburg - Veldverlichting Diepenbeek';

    private const CLIENT_NAME = 'TC Tenkie';

    protected function setUp(): void
    {
        parent::setUp();
        MirrorRelation::create(['id' => 100, 'name' => self::CLIENT_NAME]);
        MirrorProject::create([
            'id' => 'P20260050',
            'name' => self::CLIENT_NAME,
            'descr' => self::PROJECT_NAME,
            'relation_id' => 100,
            'fl_active' => true,
        ]);
    }

    public function test_watchdog_log_mail_and_prompt_show_id_project_name_and_client(): void
    {
        Mail::fake();
        config(['performance.watchdog.vanguard_email' => 'boss@example.test']);
        MirrorCost::create(['id' => 'C1', 'project_id' => 'P20260050', 'cost_price' => 30000, 'quantity' => 1, 'date' => '2026-06-15']);

        $logged = [];
        Log::shouldReceive('warning')->andReturnUsing(function ($m) use (&$logged) {
            $logged[] = $m;
        });
        Log::shouldReceive('info', 'error')->zeroOrMoreTimes();

        $prompt = null;
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateStructuredResponse')->once()->andReturnUsing(function ($p) use (&$prompt) {
            $prompt = $p;

            return [];
        });

        (new CashFlowWatchdogService($gemini))->generateRiskReport();

        foreach ([$prompt, implode("\n", $logged)] as $text) {
            $this->assertStringContainsString('P20260050', $text);
            $this->assertStringContainsString(self::PROJECT_NAME, $text);
            $this->assertStringContainsString(self::CLIENT_NAME, $text);
        }
        Mail::assertSent(VanguardImmediateAlertMail::class, function ($mail) {
            return $mail->projectData['name'] === self::PROJECT_NAME
                && $mail->projectData['client_name'] === self::CLIENT_NAME;
        });
    }

    public function test_employee_performance_project_details_use_project_name(): void
    {
        $labor = MirrorLabor::create([
            'id' => 1, 'project_id' => 'P20260050', 'employee_id' => 1,
            'labor_id' => 1, 'hours' => 8, 'date' => '2026-06-15',
        ]);

        $service = new EmployeePerformanceService(Mockery::mock(TechnicianAnalysisService::class));
        $details = $service->getTemporalProjectDetails(collect([$labor]));

        $this->assertSame(self::PROJECT_NAME, $details[0]['project_name']);
    }
}
