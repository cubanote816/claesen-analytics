<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\FieldOps\Models\TerrainType;
use Modules\Intelligence\Jobs\TranslateModelAttributesJob;
use Modules\Intelligence\Services\GeminiService;
use Tests\TestCase;

class TranslateModelAttributesJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Insert via raw DB to bypass HasAiTranslations boot hooks.
     * RefreshDatabase does NOT wrap individual tests in transactions, so
     * DB::afterCommit fires on Eloquent create() and would call the real
     * GeminiService before the mock is invoked.
     */
    private function makeType(array $translations): TerrainType
    {
        $id = DB::table('fo_terrain_types')->insertGetId([
            'type'       => json_encode($translations),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return TerrainType::find($id);
    }

    private function job(TerrainType $model, string $locale = 'nl'): TranslateModelAttributesJob
    {
        return new TranslateModelAttributesJob(TerrainType::class, $model->id, ['type'], $locale);
    }

    // ── Test 1: Traduce locales faltantes y marca complete ────────────────────

    public function test_translates_missing_locales_and_marks_complete(): void
    {
        $model = $this->makeType([
            'nl' => 'Sportveld',
            'en' => 'Sports Field',
            'fr' => 'Terrain de sport',
        ]);

        $gemini = $this->mock(GeminiService::class);
        $gemini->expects('translateAndDetect')
            ->once()
            ->with('Sportveld', ['de'])
            ->andReturn(['detected_locale' => 'nl', 'translations' => ['de' => 'Sportplatz']]);

        $this->job($model)->handle($gemini);

        $model->refresh();
        $this->assertSame('Sportplatz', $model->getTranslation('type', 'de', false));
        $this->assertSame('complete', $model->ai_translation_status);
    }

    // ── Test 2: Sin sourceText → salta atributo, status queda pending ─────────

    public function test_skips_attribute_when_source_text_is_empty(): void
    {
        $model = $this->makeType([]);

        $gemini = $this->mock(GeminiService::class);
        $gemini->expects('translateAndDetect')->never();

        $this->job($model)->handle($gemini);

        $model->refresh();
        $this->assertSame('pending', $model->ai_translation_status);
    }

    // ── Test 3: Modelo no encontrado → sale sin excepción ────────────────────

    public function test_exits_silently_when_model_not_found(): void
    {
        $gemini = $this->mock(GeminiService::class);
        $gemini->expects('translateAndDetect')->never();

        (new TranslateModelAttributesJob(TerrainType::class, 99999, ['type'], 'nl'))->handle($gemini);

        $this->assertTrue(true);
    }

    // ── Test 4: Excepción Gemini → capturada, status queda pending ────────────

    public function test_gemini_exception_is_caught_and_status_stays_pending(): void
    {
        $model = $this->makeType(['nl' => 'Sportveld']);

        $gemini = $this->mock(GeminiService::class);
        $gemini->expects('translateAndDetect')
            ->once()
            ->andThrow(new \Exception('Gemini API unavailable'));

        $this->job($model)->handle($gemini);

        $model->refresh();
        $this->assertSame('pending', $model->ai_translation_status);
    }

    // ── Test 5: Status pending si Gemini devuelve traducciones incompletas ─────

    public function test_status_is_pending_when_translation_result_is_incomplete(): void
    {
        $model = $this->makeType(['nl' => 'Sportveld', 'en' => 'Sports Field']);

        $gemini = $this->mock(GeminiService::class);
        $gemini->expects('translateAndDetect')
            ->once()
            ->with('Sportveld', ['fr', 'de'])
            ->andReturn([
                'detected_locale' => 'nl',
                'translations'    => ['fr' => 'Terrain de sport'],
            ]);

        $this->job($model)->handle($gemini);

        $model->refresh();
        $this->assertSame('Terrain de sport', $model->getTranslation('type', 'fr', false));
        $this->assertSame('pending', $model->ai_translation_status);
    }

    // ── Test 6: Todos locales completos → status complete sin llamar Gemini ────

    public function test_marks_complete_without_gemini_call_when_all_locales_present(): void
    {
        $model = $this->makeType([
            'nl' => 'Sportveld',
            'en' => 'Sports Field',
            'fr' => 'Terrain de sport',
            'de' => 'Sportplatz',
        ]);

        $gemini = $this->mock(GeminiService::class);
        $gemini->expects('translateAndDetect')->never();

        $this->job($model)->handle($gemini);

        $model->refresh();
        $this->assertSame('complete', $model->ai_translation_status);
    }
}
