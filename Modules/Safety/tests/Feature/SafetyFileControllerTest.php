<?php

declare(strict_types=1);

namespace Modules\Safety\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Safety\Models\Answer;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Inspection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SafetyFileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('safety.disk'));
        Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    private function userWithRole(string $role)
    {
        $user  = UserFactory::new()->create();
        $model = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($model);

        return $user;
    }

    private function makeInspectionWithPdf($owner): Inspection
    {
        $checklist  = Checklist::factory()->create();
        $pdfPath    = "safety-inspections/{$owner->id}/report.pdf";
        Storage::disk(config('safety.disk'))->put($pdfPath, '%PDF-1.4 fake');

        return Inspection::factory()->create([
            'user_id'      => $owner->id,
            'checklist_id' => $checklist->id,
            'pdf_path'     => $pdfPath,
        ]);
    }

    private function makeInspectionWithPhoto($owner): array
    {
        $checklist  = Checklist::factory()->create();
        $inspection = Inspection::factory()->create([
            'user_id'      => $owner->id,
            'checklist_id' => $checklist->id,
        ]);

        $photoPath = "safety-inspections/{$inspection->id}/photo.jpg";
        Storage::disk(config('safety.disk'))->put(
            $photoPath,
            UploadedFile::fake()->image('photo.jpg')->getContent()
        );

        $answer = Answer::factory()->create([
            'inspection_id' => $inspection->id,
            'photo_path'    => $photoPath,
        ]);

        return [$inspection, $answer];
    }

    // --- PDF route ---

    public function test_unauthenticated_pdf_request_redirects_to_login(): void
    {
        $owner      = $this->userWithRole('project_manager');
        $inspection = $this->makeInspectionWithPdf($owner);

        $this->get(route('safety.admin.pdf', $inspection))
            ->assertRedirectToRoute('filament.admin.auth.login');
    }

    public function test_owner_gets_200_pdf_inline(): void
    {
        $owner      = $this->userWithRole('project_manager');
        $inspection = $this->makeInspectionWithPdf($owner);

        $this->actingAs($owner)
            ->get(route('safety.admin.pdf', $inspection))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_foreign_user_gets_403_on_pdf(): void
    {
        $owner   = $this->userWithRole('project_manager');
        $foreign = $this->userWithRole('project_manager');
        $inspection = $this->makeInspectionWithPdf($owner);

        $this->actingAs($foreign)
            ->get(route('safety.admin.pdf', $inspection))
            ->assertForbidden();
    }

    public function test_missing_pdf_file_returns_404(): void
    {
        $owner      = $this->userWithRole('project_manager');
        $checklist  = Checklist::factory()->create();
        $inspection = Inspection::factory()->create([
            'user_id'      => $owner->id,
            'checklist_id' => $checklist->id,
            'pdf_path'     => 'safety-inspections/nonexistent/report.pdf',
        ]);

        $this->actingAs($owner)
            ->get(route('safety.admin.pdf', $inspection))
            ->assertNotFound();
    }

    // --- Photo route ---

    public function test_unauthenticated_photo_request_redirects_to_login(): void
    {
        $owner = $this->userWithRole('project_manager');
        [, $answer] = $this->makeInspectionWithPhoto($owner);

        $this->get(route('safety.admin.photo', $answer))
            ->assertRedirectToRoute('filament.admin.auth.login');
    }

    public function test_owner_gets_200_photo(): void
    {
        $owner = $this->userWithRole('project_manager');
        [, $answer] = $this->makeInspectionWithPhoto($owner);

        $this->actingAs($owner)
            ->get(route('safety.admin.photo', $answer))
            ->assertOk();
    }

    public function test_foreign_user_gets_403_on_photo(): void
    {
        $owner   = $this->userWithRole('project_manager');
        $foreign = $this->userWithRole('project_manager');
        [, $answer] = $this->makeInspectionWithPhoto($owner);

        $this->actingAs($foreign)
            ->get(route('safety.admin.photo', $answer))
            ->assertForbidden();
    }

    public function test_super_admin_gets_200_pdf_for_foreign_inspection(): void
    {
        $owner = $this->userWithRole('project_manager');
        $admin = $this->userWithRole('super_admin');
        $inspection = $this->makeInspectionWithPdf($owner);

        $this->actingAs($admin)
            ->get(route('safety.admin.pdf', $inspection))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_super_admin_gets_200_photo_for_foreign_inspection(): void
    {
        $owner = $this->userWithRole('project_manager');
        $admin = $this->userWithRole('super_admin');
        [, $answer] = $this->makeInspectionWithPhoto($owner);

        $this->actingAs($admin)
            ->get(route('safety.admin.photo', $answer))
            ->assertOk();
    }

    public function test_missing_photo_file_returns_404(): void
    {
        $owner     = $this->userWithRole('project_manager');
        $checklist = Checklist::factory()->create();
        $inspection = Inspection::factory()->create([
            'user_id'      => $owner->id,
            'checklist_id' => $checklist->id,
        ]);

        $answer = Answer::factory()->create([
            'inspection_id' => $inspection->id,
            'photo_path'    => 'safety-inspections/nonexistent/photo.jpg',
        ]);

        $this->actingAs($owner)
            ->get(route('safety.admin.photo', $answer))
            ->assertNotFound();
    }
}
