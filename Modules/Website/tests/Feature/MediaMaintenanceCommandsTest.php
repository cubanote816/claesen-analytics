<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Console\MigrateProjectMediaToPrivateDiskCommand;
use Modules\Website\Console\RegenerateProjectMediaCommand;
use Modules\Website\Models\Project;
use Tests\TestCase;

/**
 * F3/CLA-467 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Covers the three operational commands this ticket adds/hardens:
 * website:regenerate-media (overlap guard added), website:migrate-media-
 * to-private-disk (new, one-time), and the two focal-point/usage-rights
 * record-keeping commands.
 */
final class MediaMaintenanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        // Same defence as PrivateMediaOriginalsTest — 'gallery' media saves
        // synchronously dispatch GenerateGalleryMediaMetadataJob, which
        // would otherwise hit the real Gemini API.
        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('generateMediaMetadata')->andReturn([
                'caption' => ['nl' => 'c', 'en' => 'c', 'fr' => 'c', 'de' => 'c'],
                'alt' => ['nl' => 'a', 'en' => 'a', 'fr' => 'a', 'de' => 'a'],
            ]);
        });
    }

    public function test_regenerate_media_command_refuses_to_run_while_another_run_holds_the_lock(): void
    {
        $lock = Cache::lock('website:regenerate-media:lock', 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan(RegenerateProjectMediaCommand::class)
                ->expectsOutputToContain('already in progress')
                ->assertExitCode(0);
        } finally {
            $lock->release();
        }
    }

    public function test_migrate_media_to_private_disk_moves_a_legacy_public_disk_row(): void
    {
        $project = Project::factory()->create();

        // Simulate a media row created before this ticket: physically on
        // the public disk, with its own `disk` column still saying so —
        // Project::registerMediaCollections() config alone does not
        // retroactively change what an existing row's `disk` column says.
        $media = $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');
        $originalPath = $media->getPathRelativeToRoot();
        Storage::disk('public')->put($originalPath, Storage::disk('local')->get($originalPath));
        Storage::disk('local')->delete($originalPath);
        $media->disk = 'public';
        $media->saveQuietly();

        $this->assertTrue(Storage::disk('public')->exists($originalPath));
        $this->assertFalse(Storage::disk('local')->exists($originalPath));

        $this->artisan(MigrateProjectMediaToPrivateDiskCommand::class)->assertExitCode(0);

        $media->refresh();
        $this->assertSame('local', $media->disk);
        $this->assertTrue(Storage::disk('local')->exists($originalPath));
        $this->assertFalse(Storage::disk('public')->exists($originalPath));
        $this->assertTrue(Storage::disk('public')->exists($media->getPathRelativeToRoot('thumb')));
    }

    public function test_migrate_media_to_private_disk_is_a_no_op_the_second_time(): void
    {
        $project = Project::factory()->create();
        $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');

        $this->artisan(MigrateProjectMediaToPrivateDiskCommand::class)
            ->expectsOutputToContain('nothing to migrate')
            ->assertExitCode(0);
    }

    public function test_migrate_media_to_private_disk_dry_run_does_not_move_anything(): void
    {
        $project = Project::factory()->create();
        $media = $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');
        $originalPath = $media->getPathRelativeToRoot();
        Storage::disk('public')->put($originalPath, Storage::disk('local')->get($originalPath));
        $media->disk = 'public';
        $media->saveQuietly();

        $this->artisan(MigrateProjectMediaToPrivateDiskCommand::class, ['--dry-run' => true])
            ->expectsOutputToContain('[DRY RUN]')
            ->assertExitCode(0);

        $this->assertSame('public', $media->fresh()->disk);
        $this->assertTrue(Storage::disk('public')->exists($originalPath));
    }

    public function test_set_media_focal_point_validates_bounds_and_persists(): void
    {
        $project = Project::factory()->create();
        $media = $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');

        $this->artisan('website:set-media-focal-point', [
            'media' => $media->id,
            'x' => '0.25',
            'y' => '0.75',
        ])->assertExitCode(0);

        $this->assertSame(['x' => 0.25, 'y' => 0.75], $media->fresh()->getCustomProperty('focal_point'));

        $this->artisan('website:set-media-focal-point', [
            'media' => $media->id,
            'x' => '1.5',
            'y' => '0.5',
        ])->assertExitCode(1);
    }

    public function test_set_media_focal_point_rejects_a_non_project_media_id(): void
    {
        $this->artisan('website:set-media-focal-point', [
            'media' => 999999,
            'x' => '0.5',
            'y' => '0.5',
        ])->assertExitCode(1);
    }

    public function test_confirm_media_usage_rights_requires_by_and_persists(): void
    {
        $project = Project::factory()->create();
        $media = $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');

        $this->artisan('website:confirm-media-usage-rights', ['media' => $media->id])
            ->assertExitCode(1);

        $this->artisan('website:confirm-media-usage-rights', [
            'media' => $media->id,
            '--by' => 'admin@claesen-verlichting.be',
        ])->assertExitCode(0);

        $media->refresh();
        $this->assertSame('admin@claesen-verlichting.be', $media->getCustomProperty('usage_rights_confirmed_by'));
        $this->assertNotNull($media->getCustomProperty('usage_rights_confirmed_at'));
        $this->assertNull($media->getCustomProperty('privacy_reviewed_at'));
    }

    public function test_confirm_media_usage_rights_can_also_record_the_privacy_review_in_the_same_run(): void
    {
        $project = Project::factory()->create();
        $media = $project->addMedia($this->fakeImage())->preservingOriginal()->toMediaCollection('gallery');

        $this->artisan('website:confirm-media-usage-rights', [
            'media' => $media->id,
            '--by' => 'admin@claesen-verlichting.be',
            '--privacy-reviewed' => true,
        ])->assertExitCode(0);

        $media->refresh();
        $this->assertSame('admin@claesen-verlichting.be', $media->getCustomProperty('privacy_reviewed_by'));
        $this->assertNotNull($media->getCustomProperty('privacy_reviewed_at'));
    }

    private function fakeImage(): UploadedFile
    {
        // Must return the UploadedFile object itself, not its ->getPathname()
        // string — Illuminate\Http\Testing\File deletes its own underlying
        // temp file once nothing references the object, which happens
        // immediately if only the path string survives this method call.
        return UploadedFile::fake()->image('test.jpg', 1000, 800);
    }
}
