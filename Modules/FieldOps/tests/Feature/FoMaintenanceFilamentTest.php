<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\FieldOps\Filament\Resources\ComplexResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Services\LuminaireReplacementService;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoMaintenanceFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(GeminiService::class, fn ($m) => $m->shouldReceive('translateAndDetect')->andReturn(['translations' => [], 'detected_locale' => 'nl']));
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_maintenance_history_only_exposes_list_and_view_pages(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $luminaire = Luminaire::factory()->create();
        FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => FoMaintenanceType::factory()->preventive()->create()->id,
        ]);
        ElectricalBoard::factory()->create();

        $this->actingAs($user);

        $this->get('/fo-maintenance-records')->assertOk();
        $this->get('/fo-maintenance-records/create')->assertNotFound();
        $record = FoMaintenanceRecord::first();
        $this->get("/fo-maintenance-records/{$record->id}")->assertOk();
        $this->get("/fo-maintenance-records/{$record->id}/edit")->assertNotFound();

        self::assertFalse(\Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource::canCreate());
        self::assertFalse(\Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource::canEdit($record));
        self::assertFalse(\Modules\FieldOps\Filament\Resources\FoMaintenanceRecordResource::canDelete($record));

        $this->get('/catalogs/fo-maintenance-types')->assertOk();
        $this->get('/catalogs/fo-maintenance-types/create')->assertOk();
    }

    public function test_view_page_renders_emergency_client_reported_record(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $board = ElectricalBoard::factory()->create();
        $record = FoMaintenanceRecord::factory()->forMaintainable($board)->clientReported()->create();

        $this->get("/fo-maintenance-records/{$record->id}")->assertOk();
    }

    public function test_view_page_renders_resolved_preventive_record_without_incident_section(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $luminaire = Luminaire::factory()->create();
        $record = FoMaintenanceRecord::factory()->forMaintainable($luminaire)->create([
            'fo_maintenance_type_id' => FoMaintenanceType::factory()->preventive()->create()->id,
            'problem_reported_at' => null,
            'problem_solved_at' => null,
        ]);

        $this->get("/fo-maintenance-records/{$record->id}")->assertOk();
    }

    public function test_luminaire_scoped_history_and_return_navigation_render(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $target = Luminaire::factory()->create();
        $other = Luminaire::factory()->create();
        $targetType = FoMaintenanceType::factory()->preventive()->create();
        $targetType->setTranslation('name', 'en', 'Target inspection')->save();
        $otherType = FoMaintenanceType::factory()->corrective()->create();
        $otherType->setTranslation('name', 'en', 'Other inspection')->save();

        FoMaintenanceRecord::factory()->forMaintainable($target)->create(['fo_maintenance_type_id' => $targetType->id]);
        FoMaintenanceRecord::factory()->forMaintainable($other)->create(['fo_maintenance_type_id' => $otherType->id]);

        $this->withHeader('Accept-Language', 'en-US')->get("/fo-maintenance-records?luminaire={$target->id}")
            ->assertOk()
            ->assertSee('Target inspection')
            ->assertDontSee('Other inspection')
            ->assertSee('Back to luminaire');

        $this->withHeader('Accept-Language', 'en-US')->get("/luminaires/{$target->id}")
            ->assertOk()
            ->assertSee('Schedule maintenance');
    }

    public function test_luminaire_scoped_history_breadcrumb_reflects_full_hierarchy(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $complex = Complex::factory()->create(['name' => 'Stadion Bleukens']);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain->id);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure->id);
        $luminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id, 'frame_position' => 1]);

        $html = $this->get("/fo-maintenance-records?luminaire={$luminaire->id}&position={$luminaire->luminaire_position_id}")
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('href="'.ComplexResource::getUrl().'"', $html);
    }

    public function test_replacement_record_shows_old_and_new_luminaires_at_same_position(): void
    {
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);

        $previous = Luminaire::factory()->create(['serial_number' => 'OLD-SERIAL-001']);
        $previous->luminaireType()->update(['product_family' => 'Old ArenaVision']);
        $replacementType = LuminaireType::factory()->create(['product_family' => 'New OptiVision']);

        $result = app(LuminaireReplacementService::class)->replace($previous, [
            'luminaire_type_id' => $replacementType->id,
            'luminaire_subgroup_id' => $replacementType->luminaire_subgroup_id,
            'serial_number' => 'NEW-SERIAL-002',
            'replacement_reason' => 'Optical unit damaged beyond repair',
            'position_version' => $previous->position->position_version,
        ], $user->id);

        $this->withHeader('Accept-Language', 'en-US')
            ->get("/fo-maintenance-records/{$result['maintenance']->id}")
            ->assertOk()
            ->assertSee('Luminaire replacement')
            ->assertSee('Old ArenaVision')
            ->assertSee('OLD-SERIAL-001')
            ->assertSee('New OptiVision')
            ->assertSee('NEW-SERIAL-002')
            ->assertSee('Optical unit damaged beyond repair')
            ->assertSee(LuminaireResource::getUrl('view', ['record' => $result['previous']]), false)
            ->assertSee(LuminaireResource::getUrl('view', ['record' => $result['current']]), false);
    }
}
