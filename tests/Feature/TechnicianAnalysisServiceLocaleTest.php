<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Cafca\Models\Employee;
use Modules\Performance\Services\TechnicianAnalysisService;
use Tests\TestCase;

class TechnicianAnalysisServiceLocaleTest extends TestCase
{
    use RefreshDatabase;

    // Test subclass exposes protected methods without making them part of the public API.
    private function makeTestService(): object
    {
        return new class extends TechnicianAnalysisService {
            public function exposeLocale(): string
            {
                return $this->resolveLocale();
            }

            public function exposePrompt(string $locale, string $name, string $json): string
            {
                return $this->buildPrompt($locale, $name, $json);
            }
        };
    }

    public function test_config_nl_produces_dutch_prompt(): void
    {
        $service = $this->makeTestService();
        $prompt  = $service->exposePrompt('nl', 'Jan Pieters', '{}');

        $this->assertStringContainsString('NEDERLANDS', $prompt);
        $this->assertStringNotContainsString('ESPAÑOL', $prompt);
        $this->assertStringNotContainsString('TAREA', $prompt);
    }

    public function test_config_en_produces_english_prompt(): void
    {
        $service = $this->makeTestService();
        $prompt  = $service->exposePrompt('en', 'Jan Pieters', '{}');

        $this->assertStringContainsString('ENGLISH', $prompt);
        $this->assertStringNotContainsString('ESPAÑOL', $prompt);
        $this->assertStringNotContainsString('TAREA', $prompt);
    }

    public function test_invalid_locale_in_config_falls_back_to_nl(): void
    {
        config()->set('performance.ai_insight_locale', 'fr');

        $service = $this->makeTestService();

        $this->assertSame('nl', $service->exposeLocale());
    }

    public function test_v2_cache_key_skips_gemini_and_persists_insight(): void
    {
        Http::fake();

        Employee::create(['id' => 'TEST01', 'name' => 'Test Employee']);

        $fakeResult = [
            'archetype_label'    => 'The Diesel',
            'archetype_icon'     => '🚜',
            'efficiency_trend'   => 'STABLE',
            'burnout_risk_score' => 10,
            'manager_insight'    => 'Consistent performer.',
        ];

        Cache::put(
            'technician_archetype_v2_' . md5('TEST01'),
            $fakeResult,
            now()->addDays(7)
        );

        app(TechnicianAnalysisService::class)->analyzeTechnician('TEST01', 'Test Employee');

        Http::assertNothingSent();

        $this->assertDatabaseHas('performance_employee_insights', [
            'employee_id'     => 'TEST01',
            'archetype_label' => 'The Diesel',
        ]);
    }
}
