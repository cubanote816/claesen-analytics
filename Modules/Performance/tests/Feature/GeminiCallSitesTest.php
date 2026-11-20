<?php

declare(strict_types=1);

namespace Modules\Performance\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Modules\Cafca\Models\Employee;
use Modules\Intelligence\Services\GoogleServiceAccountAuthService;
use Modules\Intelligence\Support\GeminiEndpoint;
use Modules\Performance\Services\AuditProjectService;
use Modules\Performance\Services\TechnicianAnalysisService;
use Tests\TestCase;

/** CLA-729 S2/S3/S4 for the Performance call sites. */
final class GeminiCallSitesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.url' => null,
            'services.gemini.location' => 'eu',
            'services.gemini.model' => 'gemini-3-test-model',
        ]);

        $this->mock(GoogleServiceAccountAuthService::class, function ($mock): void {
            $mock->shouldReceive('getAccessToken')->andReturn('fake-token');
        });

        Http::preventStrayRequests();
    }

    private function fakeAnswer(array $payload): string
    {
        $url = GeminiEndpoint::generateContentUrl();
        $this->assertStringContainsString('eu-aiplatform.googleapis.com', $url);
        $this->assertStringContainsString('gemini-3-test-model', $url);

        Http::fake([$url => Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($payload)]]]]]])]);

        return $url;
    }

    private function assertMigratedRequest(string $url): void
    {
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($url): bool {
            $config = $request['generationConfig'] ?? [];

            return $request->url() === $url
                && ($config['thinkingConfig']['thinkingLevel'] ?? null) === 'MINIMAL'
                && ($config['responseMimeType'] ?? null) === 'application/json'
                && ! array_key_exists('temperature', $config)
                && ! array_key_exists('topK', $config);
        });
    }

    public function test_audit_project_service_uses_builder_url_and_migrated_payload(): void
    {
        $url = $this->fakeAnswer(['user_display' => 'Audit ok', 'system_dna' => ['critical_leak' => 'x']]);

        $result = app(AuditProjectService::class)->auditProject(
            ['category' => 'Sport', 'invoiced' => 1000],
            ['total_profit' => 10, 'margin' => 1, 'meta_labor' => 5, 'efficiency_score' => 50],
        );

        $this->assertSame('Audit ok', $result['user_display']);
        $this->assertMigratedRequest($url);
    }

    public function test_technician_analysis_service_uses_builder_url_and_migrated_payload(): void
    {
        // Never reach the real ERP: point the legacy sqlsrv connection to an in-memory table.
        config(['database.connections.sqlsrv' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('sqlsrv');
        Schema::connection('sqlsrv')->create('followup_labor_analytical', function ($table): void {
            $table->string('seqnr')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('project_id')->nullable();
            $table->float('hours')->nullable();
            $table->dateTime('date')->nullable();
        });

        Cache::forget('technician_archetype_v2_'.md5('T729'));
        Employee::create(['id' => 'T729', 'name' => 'Test Tech']);

        $url = $this->fakeAnswer([
            'archetype_label' => 'The Diesel',
            'archetype_icon' => 'D',
            'efficiency_trend' => 'STABLE',
            'burnout_risk_score' => 10,
            'manager_insight' => 'Consistent.',
        ]);

        $result = app(TechnicianAnalysisService::class)->analyzeTechnician('T729', 'Test Tech');

        $this->assertSame('The Diesel', $result['archetype_label']);
        $this->assertMigratedRequest($url);
    }

    public function test_audit_project_service_omits_thinking_config_for_a_2x_override_url(): void
    {
        $url = 'https://us-central1-aiplatform.googleapis.com/v1/projects/p/locations/us-central1/publishers/google/models/gemini-2.5-flash:generateContent';
        config(['services.gemini.url' => $url]);
        Http::fake([$url => Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"user_display":"Audit ok"}']]]]]])]);

        $result = app(AuditProjectService::class)->auditProject(
            ['category' => 'Sport', 'invoiced' => 1000],
            ['total_profit' => 10, 'margin' => 1, 'meta_labor' => 5, 'efficiency_score' => 50],
        );

        $this->assertSame('Audit ok', $result['user_display']);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) use ($url): bool {
            $config = $request['generationConfig'] ?? [];

            return $request->url() === $url
                && ! array_key_exists('thinkingConfig', $config)
                && ($config['responseMimeType'] ?? null) === 'application/json';
        });
    }
}
