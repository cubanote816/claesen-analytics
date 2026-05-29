<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Inspection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InspectionDownloadPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('safety.disk'));
        // ChecklistObserver queries these roles on every Checklist save; they must exist first
        Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function userWithRole(string $role): array
    {
        $user  = UserFactory::new()->create();
        $model = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($model);
        $token = $user->createToken('test', ['role:safety-access'])->plainTextToken;

        return [$user, $token];
    }

    private function makeInspection($owner, ?string $pdfPath = null): Inspection
    {
        $checklist = Checklist::factory()->create();

        return Inspection::factory()->create([
            'user_id'      => $owner->id,
            'checklist_id' => $checklist->id,
            'pdf_path'     => $pdfPath,
        ]);
    }

    public function test_returns_202_when_pdf_is_pending(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $inspection = $this->makeInspection($owner, null);

        $this->withToken($token)
            ->getJson("/api/v1/safety/inspections/{$inspection->id}/pdf")
            ->assertStatus(202)
            ->assertJsonPath('pdf_status', 'pending');
    }

    public function test_returns_404_when_pdf_path_set_but_file_missing(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        // pdf_path set but no actual file on the fake disk
        $inspection = $this->makeInspection($owner, 'safety-inspections/' . \Illuminate\Support\Str::uuid() . '/report.pdf');

        $this->withToken($token)
            ->getJson("/api/v1/safety/inspections/{$inspection->id}/pdf")
            ->assertStatus(404)
            ->assertJsonPath('pdf_status', 'failed');
    }

    public function test_returns_200_pdf_stream_when_file_exists(): void
    {
        [$owner, $token] = $this->userWithRole('project_manager');
        $pdfPath = "safety-inspections/test-inspection/report.pdf";
        Storage::disk(config('safety.disk'))->put($pdfPath, '%PDF-1.4 fake content');
        $inspection = $this->makeInspection($owner, $pdfPath);

        $response = $this->withToken($token)
            ->getJson("/api/v1/safety/inspections/{$inspection->id}/pdf");

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_foreign_user_gets_403(): void
    {
        [$owner]          = $this->userWithRole('project_manager');
        [, $foreignToken] = $this->userWithRole('project_manager');
        $inspection = $this->makeInspection($owner, null);

        $this->withToken($foreignToken)
            ->getJson("/api/v1/safety/inspections/{$inspection->id}/pdf")
            ->assertForbidden();
    }

    public function test_super_admin_can_download_foreign_inspection_pdf(): void
    {
        [$owner]        = $this->userWithRole('project_manager');
        [, $adminToken] = $this->userWithRole('super_admin');
        $pdfPath = "safety-inspections/test-admin/report.pdf";
        Storage::disk(config('safety.disk'))->put($pdfPath, '%PDF-1.4 fake content');
        $inspection = $this->makeInspection($owner, $pdfPath);

        $this->withToken($adminToken)
            ->getJson("/api/v1/safety/inspections/{$inspection->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
