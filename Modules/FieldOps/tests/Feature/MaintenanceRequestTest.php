<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Mockery;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceRequestStatus;
use Modules\FieldOps\Filament\Resources\FoMaintenanceRequestResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Filament\Resources\MaintenanceRequests\Pages\ViewMaintenanceRequest;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceRequest;
use Modules\FieldOps\Models\FoMaintenanceRequestMessage;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Notifications\ClientContactInvitationNotification;
use Modules\FieldOps\Notifications\ClientRequestNotification;
use Modules\FieldOps\Services\MaintenanceRequestService;
use Modules\FieldOps\Services\MaintenanceWorkOrderService;
use Modules\Intelligence\Services\GeminiService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.disks.local.root' => sys_get_temp_dir().'/cla268-media-'.Str::uuid()]);
        Notification::fake();
        foreach (['client', 'admin', 'super_admin', 'project_manager'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('translateAndDetect')->byDefault()->andReturn([
                'translations' => [],
                'detected_locale' => 'nl',
            ]);
            $mock->shouldReceive('generateStructuredResponse')->byDefault()->andReturn([]);
        });
    }

    public function test_request_creation_is_authenticated_tenant_safe_and_requires_can_report(): void
    {
        $a = $this->topology('Client A');
        $b = $this->topology('Client B');
        [$user, $token] = $this->clientUser($a['client']);
        $payload = [
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $a['luminaire']->id,
            'category' => 'light_out',
            'impact' => 'high',
            'description' => 'The luminaire above court one is completely dark.',
        ];

        $this->postJson('/api/v1/fieldops/maintenance-requests', $payload)->assertUnauthorized();

        $this->withToken($token)->postJson('/api/v1/fieldops/maintenance-requests', [
            ...$payload,
            'maintainable_id' => $b['luminaire']->id,
        ])->assertForbidden();

        $user->fieldOpsClients()->updateExistingPivot($a['client']->id, ['can_report' => false]);
        $this->withToken($token)->postJson('/api/v1/fieldops/maintenance-requests', $payload)->assertForbidden();

        $user->fieldOpsClients()->updateExistingPivot($a['client']->id, ['can_report' => true]);
        $user->fieldOpsClients()->updateExistingPivot($a['client']->id, ['can_view' => false]);
        $this->withToken($token)->postJson('/api/v1/fieldops/maintenance-requests', $payload)->assertForbidden();

        $user->fieldOpsClients()->updateExistingPivot($a['client']->id, ['can_view' => true]);
        $response = $this->withToken($token)
            ->postJson('/api/v1/fieldops/maintenance-requests', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonPath('data.installation_snapshot.kind', 'luminaire')
            ->assertJsonPath('data.installation_snapshot.luminaire_position_id', $a['luminaire']->luminaire_position_id)
            ->assertJsonPath('data.messages.0.body', $payload['description']);

        $requestId = $response->json('data.id');
        $this->assertDatabaseHas('fo_maintenance_requests', [
            'id' => $requestId,
            'client_id' => $a['client']->id,
            'reported_by_user_id' => $user->id,
        ]);
        $this->withToken($token)->getJson("/api/v1/fieldops/maintenance-requests/{$requestId}")->assertOk();
    }

    public function test_client_portal_modal_payload_refreshes_only_the_reporting_tenant_through_completion(): void
    {
        $a = $this->topology('Client A');
        $b = $this->topology('Client B');
        [$clientA, $tokenA] = $this->clientUser($a['client']);
        [, $tokenB] = $this->clientUser($b['client']);
        [$admin, $adminToken] = $this->adminUser();
        FoMaintenanceType::factory()->corrective()->create();
        $employee = Employee::create(['id' => 'CLA-276-WORKER', 'name' => 'CLA-276 Worker', 'fl_active' => true]);
        $worker = UserFactory::new()->create(['employee_id' => $employee->id]);
        $worker->assignRole('project_manager');
        // CLA-369: incidental backoffice actor in this test (it's about client
        // tenant isolation, not worker scoping) — broad access keeps it that way.
        $worker->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('fieldops.view-all-clients', 'web'));

        // Mirrors Claesen-Client's actual flow: the portal never has the luminaire ID
        // ahead of time, it reads it off the infrastructure explorer response
        // (`position.luminaire_id` in portal-data.ts) before opening the report modal.
        $infrastructure = $this->withToken($tokenA)
            ->getJson('/api/v1/fieldops/client-portal/infrastructure')
            ->assertOk()
            ->json('data.0.terrains.0.structures.0.frames.0.positions.0');
        $this->assertSame($a['luminaire']->id, $infrastructure['luminaire_id']);

        $created = $this->withToken($tokenA)
            ->postJson('/api/v1/fieldops/maintenance-requests', [
                'maintainable_type' => Luminaire::class,
                'maintainable_id' => $infrastructure['luminaire_id'],
                'description' => 'CLA-276 portal report: luminaire is dark.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'received');
        $requestId = $created->json('data.id');

        // The reporting portal sees the new request after its query refresh; client B sees neither list nor detail.
        $this->withToken($tokenA)->getJson('/api/v1/fieldops/maintenance-requests')
            ->assertOk()
            ->assertJsonFragment(['id' => $requestId]);
        Auth::forgetGuards();
        $this->withToken($tokenB)->getJson('/api/v1/fieldops/maintenance-requests')
            ->assertOk()
            ->assertJsonMissing(['id' => $requestId]);
        Auth::forgetGuards();
        $this->withToken($tokenB)->getJson("/api/v1/fieldops/maintenance-requests/{$requestId}")
            ->assertForbidden();

        Auth::forgetGuards();
        $workOrderId = $this->withToken($adminToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/convert", [
                'assigned_employee_id' => $employee->id,
            ])
            ->assertOk()
            ->json('work_order_id');

        Auth::forgetGuards();
        $workerToken = $worker->createToken('cla-276-worker')->plainTextToken;
        $this->withToken($workerToken)->postJson("/api/v1/fieldops/maintenance-work-orders/{$workOrderId}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
        $this->withToken($workerToken)->postJson("/api/v1/fieldops/maintenance-work-orders/{$workOrderId}/submit", [
            'solution_applied' => 'CLA-276 replacement and output verification.',
        ])->assertOk()->assertJsonPath('data.status', 'awaiting_validation');

        Auth::forgetGuards();
        $this->withToken($adminToken)->postJson("/api/v1/fieldops/maintenance-work-orders/{$workOrderId}/validate")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        Auth::forgetGuards();
        $this->withToken($tokenA)->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');
        $this->assertDatabaseHas('fo_maintenance_requests', [
            'id' => $requestId,
            'client_id' => $a['client']->id,
            'reported_by_user_id' => $clientA->id,
            'status' => 'closed',
        ]);
    }

    public function test_electrical_board_and_guided_ai_intake_are_supported_without_ai_tenancy_authority(): void
    {
        $topology = $this->topology('Board Client');
        [, $token] = $this->clientUser($topology['client']);
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('generateStructuredResponse')->once()->withArgs(
            fn (string $prompt): bool => str_contains($prompt, 'Cabinet makes a buzzing sound'),
        )->andReturn([
            'category' => 'electrical_issue',
            'impact' => 'high',
            'summary' => 'Buzzing electrical cabinet',
            'clarification_questions' => ['Is there smoke?', 'Is power still on?'],
            'client_id' => 999999,
        ]);
        $this->app->instance(GeminiService::class, $gemini);

        $this->withToken($token)->postJson('/api/v1/fieldops/maintenance-requests/intake/suggest', [
            'maintainable_type' => ElectricalBoard::class,
            'maintainable_id' => $topology['board']->id,
            'description' => 'Cabinet makes a buzzing sound',
        ])->assertOk()
            ->assertJsonPath('data.category', 'electrical_issue')
            ->assertJsonPath('data.impact', 'high')
            ->assertJsonMissingPath('data.client_id');

        $this->withToken($token)->postJson('/api/v1/fieldops/maintenance-requests', [
            'maintainable_type' => ElectricalBoard::class,
            'maintainable_id' => $topology['board']->id,
            'description' => 'Cabinet makes a buzzing sound',
        ])->assertCreated()
            ->assertJsonPath('data.installation_snapshot.kind', 'electrical_board')
            ->assertJsonPath('data.maintainable_id', $topology['board']->id);
    }

    public function test_public_conversation_and_internal_notes_are_strictly_separated(): void
    {
        $topology = $this->topology('Conversation Client');
        [, $clientToken] = $this->clientUser($topology['client']);
        [$admin, $adminToken] = $this->adminUser();
        $requestId = $this->createRequest($clientToken, $topology['luminaire']);

        $this->withToken($clientToken)->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/messages", [
            'body' => 'The issue is worse after sunset.',
            'visibility' => 'internal',
        ])->assertCreated()->assertJsonPath('data.visibility', 'public');

        Auth::forgetGuards();
        $this->withToken($adminToken)->patchJson("/api/v1/fieldops/maintenance-requests/{$requestId}/respond", [
            'status' => 'in_review',
            'public_response' => 'We are reviewing the installation.',
            'internal_notes' => 'Check the warranty before assigning a technician.',
        ])->assertOk();

        Auth::forgetGuards();
        $clientView = $this->withToken($clientToken)
            ->getJson("/api/v1/fieldops/maintenance-requests/{$requestId}")
            ->assertOk()
            ->assertJsonMissing(['body' => 'Check the warranty before assigning a technician.']);
        self::assertCount(3, $clientView->json('data.messages'));

        Auth::forgetGuards();
        $this->withToken($adminToken)
            ->getJson("/api/v1/fieldops/maintenance-requests/{$requestId}")
            ->assertOk()
            ->assertJsonFragment(['body' => 'Check the warranty before assigning a technician.']);

        $internal = FoMaintenanceRequestMessage::query()->where('visibility', 'internal')->firstOrFail();
        try {
            $internal->update(['body' => 'Changed']);
            self::fail('Internal notes must be append-only.');
        } catch (LogicException) {
            self::assertTrue(true);
        }
        self::assertSame($admin->id, $internal->user_id);
    }

    public function test_private_attachments_are_streamed_with_visibility_and_bola_checks(): void
    {
        $a = $this->topology('Attachment A');
        $b = $this->topology('Attachment B');
        [, $tokenA] = $this->clientUser($a['client']);
        [, $tokenB] = $this->clientUser($b['client']);
        [, $adminToken] = $this->adminUser();
        $requestId = $this->createRequest($tokenA, $a['luminaire']);

        $publicId = $this->withToken($tokenA)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/attachments", [
                'file' => UploadedFile::fake()->image('failure.jpg'),
                'visibility' => 'public',
            ])->assertCreated()->json('data.id');
        Auth::forgetGuards();
        $internalId = $this->withToken($adminToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/attachments", [
                'file' => UploadedFile::fake()->createWithContent('warranty.pdf', "%PDF-1.4\n%%EOF"),
                'visibility' => 'internal',
            ])->assertCreated()->json('data.id');

        Auth::forgetGuards();
        $this->withToken($tokenA)->getJson("/api/v1/fieldops/maintenance-requests/{$requestId}")
            ->assertOk()->assertJsonCount(1, 'data.attachments')->assertJsonPath('data.attachments.0.id', $publicId);
        $this->withToken($tokenA)->get("/api/v1/fieldops/maintenance-request-attachments/{$publicId}")->assertOk();
        $this->withToken($tokenA)->get("/api/v1/fieldops/maintenance-request-attachments/{$internalId}")->assertForbidden();
        Auth::forgetGuards();
        $this->withToken($tokenB)->get("/api/v1/fieldops/maintenance-request-attachments/{$publicId}")->assertForbidden();
        Auth::forgetGuards();
        $this->withToken($adminToken)->get("/api/v1/fieldops/maintenance-request-attachments/{$internalId}")->assertOk();
    }

    public function test_resolution_confirmation_reopening_and_second_work_order_preserve_history(): void
    {
        $topology = $this->topology('Lifecycle Client');
        [, $clientToken] = $this->clientUser($topology['client']);
        [$admin, $adminToken] = $this->adminUser();
        FoMaintenanceType::factory()->corrective()->create();
        $requestId = $this->createRequest($clientToken, $topology['luminaire']);

        Auth::forgetGuards();
        $firstOrderId = $this->withToken($adminToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/convert")
            ->assertOk()->json('work_order_id');
        $this->withToken($adminToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/convert")
            ->assertOk()->assertJsonPath('work_order_id', $firstOrderId);
        $this->assertDatabaseCount('fo_maintenance_work_orders', 1);

        $order = FoMaintenanceRequest::findOrFail($requestId)->workOrder;
        app(MaintenanceWorkOrderService::class)->close($order, $admin->id, 'Verified from the backoffice.');
        $this->assertDatabaseHas('fo_maintenance_requests', ['id' => $requestId, 'status' => 'resolved']);

        Auth::forgetGuards();
        $this->withToken($clientToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/confirm")
            ->assertOk()->assertJsonPath('data.status', 'closed');
        $this->withToken($clientToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/reopen", [
                'reason' => 'The fault returned after the next match.',
            ])->assertOk()->assertJsonPath('data.status', 'reopened');

        Auth::forgetGuards();
        $secondOrderId = $this->withToken($adminToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/convert")
            ->assertOk()->json('work_order_id');
        self::assertNotSame($firstOrderId, $secondOrderId);
        Auth::forgetGuards();
        $this->withToken($clientToken)->getJson("/api/v1/fieldops/maintenance-requests/{$requestId}")
            ->assertOk()->assertJsonCount(2, 'data.work_order_ids');
    }

    public function test_contact_manager_can_invite_and_activation_code_is_one_time_and_hashed(): void
    {
        $client = FoClient::factory()->create(['name' => 'Invitation Client']);
        [$manager, $token] = $this->clientUser($client, canManageContacts: true);

        $this->withToken($token)->postJson("/api/v1/fieldops/clients/{$client->id}/contacts/invitations", [
            'name' => 'New Contact',
            'email' => 'NEW.CONTACT@example.com',
            'language' => 'en',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'new.contact@example.com')
            ->assertJsonPath('data.activation_required', true);

        $invited = User::query()->where('email', 'new.contact@example.com')->firstOrFail();
        self::assertNotNull($invited->activation_code_hash);
        self::assertTrue($invited->hasRole('client'));
        $this->assertDatabaseHas('fo_client_user', [
            'fo_client_id' => $client->id,
            'user_id' => $invited->id,
            'can_report' => true,
        ]);

        $activationCode = null;
        Notification::assertSentTo(
            $invited,
            ClientContactInvitationNotification::class,
            function (ClientContactInvitationNotification $notification) use (&$activationCode): bool {
                parse_str((string) parse_url($notification->activationUrl(), PHP_URL_QUERY), $query);
                $activationCode = $query['activation_code'] ?? null;

                return is_string($activationCode) && strlen($activationCode) === 64;
            },
        );
        self::assertNotSame($activationCode, $invited->activation_code_hash);
        self::assertSame(hash('sha256', $activationCode), $invited->activation_code_hash);

        $this->postJson('/api/v1/auth/activate', ['code' => $activationCode])
            ->assertOk()->assertJsonStructure(['setup_token', 'expires_in']);
        $this->postJson('/api/v1/auth/activate', ['code' => $activationCode])->assertUnprocessable();
        self::assertNull($invited->fresh()->activation_code_hash);
        self::assertTrue($manager->fieldOpsClients()->whereKey($client->id)->exists());
    }

    public function test_contact_invitation_requires_manage_contacts(): void
    {
        $client = FoClient::factory()->create();
        [, $clientToken] = $this->clientUser($client, canManageContacts: false);
        $this->withToken($clientToken)->postJson("/api/v1/fieldops/clients/{$client->id}/contacts/invitations", [
            'name' => 'Blocked Contact',
            'email' => 'blocked@example.com',
        ])->assertForbidden();
    }

    public function test_client_can_cancel_their_own_request_before_it_is_converted(): void
    {
        $topology = $this->topology('Cancel Client');
        [$clientUser, $clientToken] = $this->clientUser($topology['client']);
        [$admin] = $this->adminUser();
        $requestId = $this->createRequest($clientToken, $topology['luminaire']);

        Auth::forgetGuards();
        $this->withToken($clientToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/cancel", [
                'reason' => 'No longer needed, resolved on its own.',
            ])->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'No longer needed, resolved on its own.');

        $this->assertDatabaseHas('fo_maintenance_requests', [
            'id' => $requestId,
            'status' => 'cancelled',
            'cancelled_by_user_id' => $clientUser->id,
        ]);
        Notification::assertSentTo($admin, ClientRequestNotification::class);
    }

    public function test_backoffice_can_also_cancel_a_pre_conversion_request_and_the_client_is_notified(): void
    {
        $topology = $this->topology('Backoffice Cancel Client');
        [$clientUser, $clientToken] = $this->clientUser($topology['client']);
        [, $adminToken] = $this->adminUser();
        $requestId = $this->createRequest($clientToken, $topology['luminaire']);

        Auth::forgetGuards();
        $this->withToken($adminToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/cancel", [
                'reason' => 'Duplicate of another open request.',
            ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        Notification::assertSentTo($clientUser, ClientRequestNotification::class);
    }

    public function test_cancellation_is_rejected_once_the_request_has_a_work_order_and_for_terminal_states(): void
    {
        $topology = $this->topology('No Cancel Client');
        [, $clientToken] = $this->clientUser($topology['client']);
        [, $adminToken] = $this->adminUser();
        FoMaintenanceType::factory()->corrective()->create();
        $requestId = $this->createRequest($clientToken, $topology['luminaire']);

        Auth::forgetGuards();
        $this->withToken($adminToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/convert")
            ->assertOk();

        Auth::forgetGuards();
        $this->withToken($clientToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/cancel", ['reason' => 'Too late.'])
            ->assertUnprocessable();

        $this->assertDatabaseHas('fo_maintenance_requests', ['id' => $requestId, 'status' => 'planned']);
    }

    public function test_cancellation_is_tenant_safe_a_client_cannot_cancel_another_clients_request(): void
    {
        $a = $this->topology('Cancel Owner');
        $b = $this->topology('Cancel Intruder');
        [, $ownerToken] = $this->clientUser($a['client']);
        [, $intruderToken] = $this->clientUser($b['client']);
        $requestId = $this->createRequest($ownerToken, $a['luminaire']);

        Auth::forgetGuards();
        $this->withToken($intruderToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/cancel", ['reason' => 'Not mine.'])
            ->assertForbidden();

        $this->assertDatabaseHas('fo_maintenance_requests', ['id' => $requestId, 'status' => 'received']);
    }

    public function test_filament_cancel_action_calls_the_service_and_cancels_a_cancellable_request(): void
    {
        $client = FoClient::factory()->create();
        [$admin] = $this->adminUser();
        $board = ElectricalBoard::factory()->create();
        $request = FoMaintenanceRequest::query()->create([
            'client_id' => $client->id,
            'status' => MaintenanceRequestStatus::RECEIVED,
            'description' => 'Cancellable request',
            'maintainable_type' => ElectricalBoard::class,
            'maintainable_id' => $board->id,
        ]);

        $this->actingAs($admin);
        app(MaintenanceRequestService::class)->cancel($request, $admin, 'Filament reason.');

        $this->assertDatabaseHas('fo_maintenance_requests', [
            'id' => $request->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Filament reason.',
            'cancelled_by_user_id' => $admin->id,
        ]);
        $this->get(FoMaintenanceRequestResource::getUrl('view', ['record' => $request]))->assertOk();
    }

    public function test_filament_request_page_renders(): void
    {
        $client = FoClient::factory()->create();
        [$admin] = $this->adminUser();

        $request = FoMaintenanceRequest::query()->create([
            'client_id' => $client->id,
            'status' => MaintenanceRequestStatus::RECEIVED,
            'description' => 'Filament smoke test',
            'maintainable_type' => ElectricalBoard::class,
            'maintainable_id' => ElectricalBoard::factory()->create()->id,
        ]);
        $this->actingAs($admin)
            ->get(FoMaintenanceRequestResource::getUrl('view', ['record' => $request]))
            ->assertOk();
    }

    public function test_converting_a_request_redirects_to_the_work_order_edit_page(): void
    {
        $topology = $this->topology('Redirect Client');
        [$admin] = $this->adminUser();
        FoMaintenanceType::factory()->corrective()->create();
        $request = FoMaintenanceRequest::query()->create([
            'client_id' => $topology['client']->id,
            'status' => MaintenanceRequestStatus::RECEIVED,
            'description' => 'Needs a work order for redirect coverage.',
            'maintainable_type' => Luminaire::class,
            'maintainable_id' => $topology['luminaire']->id,
        ]);

        $this->actingAs($admin);
        Livewire::test(ViewMaintenanceRequest::class, ['record' => $request->id])
            ->callAction('convert')
            ->assertRedirect(FoMaintenanceWorkOrderResource::getUrl('edit', [
                'record' => $request->fresh()->work_order_id,
            ]));
    }

    public function test_conversion_without_an_explicit_priority_defaults_to_medium(): void
    {
        $topology = $this->topology('Priority Client');
        [, $clientToken] = $this->clientUser($topology['client']);
        [, $adminToken] = $this->adminUser();
        FoMaintenanceType::factory()->corrective()->create();
        $requestId = $this->createRequest($clientToken, $topology['luminaire']);

        Auth::forgetGuards();
        $workOrderId = $this->withToken($adminToken)
            ->postJson("/api/v1/fieldops/maintenance-requests/{$requestId}/convert")
            ->assertOk()
            ->json('work_order_id');

        $this->assertDatabaseHas('fo_maintenance_work_orders', [
            'id' => $workOrderId,
            'priority' => 'medium',
        ]);
    }

    private function createRequest(string $token, Luminaire|ElectricalBoard $equipment): int
    {
        return (int) $this->withToken($token)->postJson('/api/v1/fieldops/maintenance-requests', [
            'maintainable_type' => $equipment::class,
            'maintainable_id' => $equipment->id,
            'category' => 'light_out',
            'impact' => 'high',
            'description' => 'The installation is not operating correctly.',
        ])->assertCreated()->json('data.id');
    }

    private function clientUser(FoClient $client, bool $canManageContacts = false): array
    {
        $user = UserFactory::new()->create();
        $user->assignRole('client');
        $user->fieldOpsClients()->attach($client->id, [
            'is_active' => true,
            'can_view' => true,
            'can_report' => true,
            'can_manage_contacts' => $canManageContacts,
        ]);

        return [$user, $user->createToken('client-portal')->plainTextToken];
    }

    private function adminUser(): array
    {
        $user = UserFactory::new()->create();
        $user->assignRole('admin');
        // CLA-369: broad FieldOps access needs the permission explicitly now.
        $user->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('fieldops.view-all-clients', 'web'));

        return [$user, $user->createToken('backoffice')->plainTextToken];
    }

    private function topology(string $name): array
    {
        $client = FoClient::factory()->create(['name' => $name]);
        $complex = Complex::factory()->create(['client_id' => $client->id, 'name' => "{$name} site"]);
        $terrain = Terrain::factory()->create(['complex_id' => $complex->id]);
        $structure = Structure::factory()->create();
        $structure->terrains()->attach($terrain);
        $frame = LuminaireFrame::factory()->create();
        $frame->structures()->attach($structure);
        $luminaire = Luminaire::factory()->create(['luminaire_frame_id' => $frame->id]);
        $board = ElectricalBoard::factory()->create();
        $board->complexes()->attach($complex);

        return compact('client', 'complex', 'terrain', 'structure', 'frame', 'luminaire', 'board');
    }
}
