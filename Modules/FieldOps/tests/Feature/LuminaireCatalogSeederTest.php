<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Database\Seeders\PlaceholderLuminaireTypeSeeder;
use Modules\FieldOps\Database\Seeders\RebuildLuminaireCatalogSeeder;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;
use Tests\TestCase;

class LuminaireCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_removes_luminaire_data_and_preserves_frames_and_unrelated_maintenance(): void
    {
        $frame = LuminaireFrame::factory()->create();
        $luminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id]);
        $deletedLuminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id]);
        $luminaireMaintenance = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create();
        $deletedLuminaireMaintenance = FoMaintenanceRecord::factory()->forMaintainable($deletedLuminaire)->create();
        $deletedLuminaire->delete();
        $deletedLuminaireMaintenance->delete();

        $electricalBoard = ElectricalBoard::factory()->create();
        $unrelatedMaintenance = FoMaintenanceRecord::factory()->forMaintainable($electricalBoard)->create();

        $this->seed(RebuildLuminaireCatalogSeeder::class);

        $this->assertDatabaseHas('fo_luminaire_frames', ['id' => $frame->id]);
        $this->assertDatabaseHas('fo_maintenance_records', ['id' => $unrelatedMaintenance->id]);
        $this->assertDatabaseMissing('fo_maintenance_records', ['id' => $luminaireMaintenance->id]);
        $this->assertDatabaseMissing('fo_maintenance_records', ['id' => $deletedLuminaireMaintenance->id]);
        $this->assertSame(0, Luminaire::withTrashed()->count());
        // +1 subgroup / +1 type: RebuildLuminaireCatalogSeeder also runs
        // PlaceholderLuminaireTypeSeeder (CLA-391) — the "unidentified fixture"
        // sentinel used by AI multi-luminaire detection must survive a rebuild.
        $this->assertSame(6, LuminaireSubgroup::query()->count());
        $this->assertSame(11, LuminaireType::query()->count());
    }

    public function test_rebuild_seeds_the_exact_catalog_with_existing_images(): void
    {
        $this->seed(RebuildLuminaireCatalogSeeder::class);

        $expectedModels = [
            'BVP518',
            'BVP528',
            'BVP418',
            'BVP428',
            'OMNISTAR LED',
            'Altis Sport LED',
            'TLC for LED®',
            'MVP507 OptiVision HID',
            'MVF024 PowerVision HID',
            'MVF403',
        ];

        $this->assertEqualsCanonicalizing(
            $expectedModels,
            LuminaireType::query()
                ->whereNotNull('model_reference')
                ->pluck('model_reference')
                ->all(),
        );

        LuminaireType::query()
            ->whereNotNull('model_reference')
            ->each(function (LuminaireType $type): void {
                $this->assertNotNull($type->product_family);
                $this->assertNotNull($type->typical_application);
                $this->assertNotNull($type->image_source_url);
                $this->assertFileExists(public_path(ltrim((string) $type->image, '/')));
            });

        // CLA-391 — the placeholder catalog entry has no model_reference/image on
        // purpose (it's a sentinel, not a real product), and it must survive a
        // rebuild since AI detection depends on its id being resolvable.
        $placeholderIds = PlaceholderLuminaireTypeSeeder::resolveIds();
        $this->assertNotNull($placeholderIds);
        $this->assertDatabaseHas('fo_luminaire_types', [
            'id' => $placeholderIds['luminaire_type_id'],
            'name' => PlaceholderLuminaireTypeSeeder::TYPE_NAME,
            'model_reference' => null,
        ]);

        $this->assertDatabaseHas('fo_luminaire_subgroups', [
            'group_name' => 'HID — legacy only',
            'brand' => 'Philips',
        ]);
        $this->assertDatabaseHas('fo_luminaire_types', [
            'product_family' => 'ArenaVision HID',
            'model_reference' => 'MVF403',
        ]);
    }
}
