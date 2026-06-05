<?php

namespace Modules\Website\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Website\Models\Project;

class WorkDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    protected function tearDown(): void
    {
        app()->setLocale('en');
        parent::tearDown();
    }

    private function createProject(array $overrides = []): Project
    {
        // withoutEvents: skips HasAiTranslations (Gemini) and ProjectObserver (GitHub webhook).
        return Project::withoutEvents(function () use ($overrides) {
            return Project::create(array_merge([
                'slug'      => 'test-project-' . uniqid(),
                'title'     => ['nl' => 'Testproject NL', 'en' => 'Test Project EN'],
                'category'  => 'sport',
                'published' => true,
            ], $overrides));
        });
    }

    // ─── Structure ───────────────────────────────────────────────────────────

    public function test_show_includes_all_work_details_keys(): void
    {
        $project = $this->createProject([
            'work_story' => ['nl' => 'Verhaal NL', 'en' => 'Story EN'],
            'challenge'  => ['nl' => 'Uitdaging NL', 'en' => 'Challenge EN'],
            'solution'   => ['nl' => 'Oplossing NL', 'en' => 'Solution EN'],
            'result'     => ['nl' => 'Resultaat NL', 'en' => 'Result EN'],
        ]);

        $this->get("/v1/website/projects/{$project->slug}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'work_story',
                    'challenge',
                    'solution',
                    'result',
                    'detail_gallery',
                ],
            ]);
    }

    // ─── Null / empty defaults ────────────────────────────────────────────────

    public function test_project_without_work_details_returns_null_fields_and_empty_gallery(): void
    {
        $project = $this->createProject();

        $response = $this->get("/v1/website/projects/{$project->slug}")->assertOk();

        $data = $response->json('data');
        $this->assertNull($data['work_story']);
        $this->assertNull($data['challenge']);
        $this->assertNull($data['solution']);
        $this->assertNull($data['result']);
        $this->assertSame([], $data['detail_gallery']);
    }

    // ─── Locale: nl ──────────────────────────────────────────────────────────

    public function test_work_story_returns_nl_translation_when_locale_is_nl(): void
    {
        $project = $this->createProject([
            'work_story' => ['nl' => 'Verhaal in het NL', 'en' => 'Story in English'],
        ]);

        app()->setLocale('nl');

        $this->get("/v1/website/projects/{$project->slug}")
            ->assertOk()
            ->assertJsonPath('data.work_story', 'Verhaal in het NL');
    }

    // ─── Locale: en ──────────────────────────────────────────────────────────

    public function test_work_story_returns_en_translation_when_locale_is_en(): void
    {
        $project = $this->createProject([
            'work_story' => ['nl' => 'Verhaal in het NL', 'en' => 'Story in English'],
        ]);

        app()->setLocale('en');

        $this->get("/v1/website/projects/{$project->slug}")
            ->assertOk()
            ->assertJsonPath('data.work_story', 'Story in English');
    }

    // ─── Locale: de fallback ─────────────────────────────────────────────────

    public function test_locale_de_uses_fallback_and_does_not_return_null(): void
    {
        // HasTranslations behaviour (empirically verified): when the requested locale
        // has no stored translation, the library resolves to the 'en' value first
        // (app default locale), not 'nl' (fallback_locale config). This means 'de'
        // requests get the English translation — non-null, non-exception.
        $project = $this->createProject([
            'work_story' => ['nl' => 'Verhaal NL', 'en' => 'Story EN'],
            'challenge'  => ['nl' => 'Uitdaging NL', 'en' => 'Challenge EN'],
        ]);

        app()->setLocale('de');

        $response = $this->get("/v1/website/projects/{$project->slug}")->assertOk();

        $this->assertNotNull($response->json('data.work_story'), 'de locale must not return null');
        $this->assertNotNull($response->json('data.challenge'), 'de locale must not return null');
    }

    // ─── detail_gallery: always array ────────────────────────────────────────

    public function test_detail_gallery_is_empty_array_not_null_when_no_images(): void
    {
        $project = $this->createProject();

        $response = $this->get("/v1/website/projects/{$project->slug}")->assertOk();

        $gallery = $response->json('data.detail_gallery');
        $this->assertIsArray($gallery);
        $this->assertCount(0, $gallery);
    }

    // ─── detail_gallery: structure with media ────────────────────────────────

    public function test_detail_gallery_returns_correct_structure_with_media(): void
    {
        Storage::fake('public');

        $project = $this->createProject();

        $file = UploadedFile::fake()->image('work-photo.jpg', 800, 600);
        $project->addMedia($file)->toMediaCollection('detail_gallery');

        $response = $this->get("/v1/website/projects/{$project->slug}")->assertOk();

        $gallery = $response->json('data.detail_gallery');
        $this->assertCount(1, $gallery, 'detail_gallery should contain the uploaded image');

        $item = $gallery[0];
        foreach (['id', 'original', 'optimized', 'gallery', 'thumb', 'caption', 'alt', 'mime_type', 'size'] as $key) {
            $this->assertArrayHasKey($key, $item, "detail_gallery item must have key [{$key}]");
        }
        $this->assertIsInt($item['id']);
        $this->assertNull($item['caption'], 'caption must be null when not set');
        $this->assertNull($item['alt'], 'alt must be null when not set');
    }

    // ─── Existing gallery is not affected ────────────────────────────────────

    public function test_main_gallery_is_not_affected_by_detail_gallery(): void
    {
        Storage::fake('public');

        $project = $this->createProject();

        // Add one image to each collection independently
        $project->addMedia(UploadedFile::fake()->image('main.jpg'))->toMediaCollection('gallery');
        $project->addMedia(UploadedFile::fake()->image('detail.jpg'))->toMediaCollection('detail_gallery');

        $response = $this->get("/v1/website/projects/{$project->slug}")->assertOk();

        $this->assertCount(1, $response->json('data.gallery'),        'main gallery must have 1 image');
        $this->assertCount(1, $response->json('data.detail_gallery'), 'detail gallery must have 1 image');
    }
}
