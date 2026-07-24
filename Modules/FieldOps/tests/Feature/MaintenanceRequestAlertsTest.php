<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\FieldOps\Enums\MaintenanceRequestStatus;
use Modules\FieldOps\Filament\Resources\MaintenanceRequests\Pages\ListMaintenanceRequests;
use Modules\FieldOps\Filament\Widgets\MaintenanceRequestLifecycleWidget;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceRequest;
use Modules\FieldOps\Models\FoMaintenanceRequestAlert;
use Modules\FieldOps\Notifications\MaintenanceRequestAlertNotification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceRequestAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        foreach (['client', 'admin', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        config([
            'fieldops.request_alerts.first_response_hours' => 24,
            'fieldops.request_alerts.confirmation_wait_days' => 7,
        ]);
    }

    public function test_fires_no_first_response_alert_and_notifies_backoffice(): void
    {
        $admin = $this->adminUser();
        $request = $this->makeRequest([
            'status' => MaintenanceRequestStatus::RECEIVED,
            'acknowledged_at' => null,
            'created_at' => now()->subHours(30),
        ]);

        $this->artisan('fieldops:check-request-alerts')->assertSuccessful();

        $this->assertDatabaseHas('fo_maintenance_request_alerts', [
            'maintenance_request_id' => $request->id,
            'alert_type' => 'no_first_response',
            'resolved_at' => null,
        ]);
        Notification::assertSentTo($admin, MaintenanceRequestAlertNotification::class);
    }

    public function test_does_not_breach_before_the_threshold(): void
    {
        $this->adminUser();
        $request = $this->makeRequest([
            'status' => MaintenanceRequestStatus::RECEIVED,
            'acknowledged_at' => null,
            'created_at' => now()->subHours(2),
        ]);

        $this->artisan('fieldops:check-request-alerts')->assertSuccessful();

        $this->assertDatabaseMissing('fo_maintenance_request_alerts', [
            'maintenance_request_id' => $request->id,
        ]);
    }

    public function test_does_not_duplicate_or_renotify_on_rerun(): void
    {
        $admin = $this->adminUser();
        $this->makeRequest([
            'status' => MaintenanceRequestStatus::RECEIVED,
            'acknowledged_at' => null,
            'created_at' => now()->subHours(30),
        ]);

        $this->artisan('fieldops:check-request-alerts')->assertSuccessful();
        $this->artisan('fieldops:check-request-alerts')->assertSuccessful();

        $this->assertDatabaseCount('fo_maintenance_request_alerts', 1);
        Notification::assertSentToTimes($admin, MaintenanceRequestAlertNotification::class, 1);
    }

    public function test_auto_resolves_once_the_backoffice_responds(): void
    {
        $this->adminUser();
        $request = $this->makeRequest([
            'status' => MaintenanceRequestStatus::RECEIVED,
            'acknowledged_at' => null,
            'created_at' => now()->subHours(30),
        ]);

        $this->artisan('fieldops:check-request-alerts')->assertSuccessful();
        $request->update(['acknowledged_at' => now()]);
        $this->artisan('fieldops:check-request-alerts')->assertSuccessful();

        $alert = FoMaintenanceRequestAlert::where('maintenance_request_id', $request->id)
            ->where('alert_type', 'no_first_response')
            ->firstOrFail();
        self::assertNotNull($alert->resolved_at);
    }

    public function test_fires_awaiting_confirmation_alert_for_a_stale_resolution(): void
    {
        $admin = $this->adminUser();
        $request = $this->makeRequest([
            'status' => MaintenanceRequestStatus::RESOLVED,
            'acknowledged_at' => now()->subDays(10),
            'resolved_at' => now()->subDays(8),
            'confirmed_at' => null,
        ]);

        $this->artisan('fieldops:check-request-alerts')->assertSuccessful();

        $this->assertDatabaseHas('fo_maintenance_request_alerts', [
            'maintenance_request_id' => $request->id,
            'alert_type' => 'awaiting_confirmation',
            'resolved_at' => null,
        ]);
        Notification::assertSentTo($admin, MaintenanceRequestAlertNotification::class);
    }

    public function test_awaiting_confirmation_alert_re_fires_after_a_reopen_cycle(): void
    {
        $admin = $this->adminUser();
        $request = $this->makeRequest([
            'status' => MaintenanceRequestStatus::RESOLVED,
            'acknowledged_at' => now()->subDays(20),
            'resolved_at' => now()->subDays(8),
            'confirmed_at' => null,
        ]);

        // First cycle: breaches, then the client confirms and it auto-resolves.
        $this->artisan('fieldops:check-request-alerts')->assertSuccessful();
        $request->update(['confirmed_at' => now(), 'status' => MaintenanceRequestStatus::CLOSED]);
        $this->artisan('fieldops:check-request-alerts')->assertSuccessful();

        $alert = FoMaintenanceRequestAlert::where('maintenance_request_id', $request->id)
            ->where('alert_type', 'awaiting_confirmation')
            ->firstOrFail();
        self::assertNotNull($alert->resolved_at);

        // Second cycle: reopened, resolved again, stuck again — same row reused, not duplicated.
        $request->update([
            'status' => MaintenanceRequestStatus::RESOLVED,
            'confirmed_at' => null,
            'resolved_at' => now()->subDays(8),
        ]);
        $this->artisan('fieldops:check-request-alerts')->assertSuccessful();

        $this->assertDatabaseCount('fo_maintenance_request_alerts', 1);
        $alert->refresh();
        self::assertNull($alert->resolved_at);
        Notification::assertSentToTimes($admin, MaintenanceRequestAlertNotification::class, 2);
    }

    public function test_dry_run_creates_nothing_and_notifies_nobody(): void
    {
        $admin = $this->adminUser();
        $this->makeRequest([
            'status' => MaintenanceRequestStatus::RECEIVED,
            'acknowledged_at' => null,
            'created_at' => now()->subHours(30),
        ]);

        $this->artisan('fieldops:check-request-alerts --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('fo_maintenance_request_alerts', 0);
        Notification::assertNotSentTo($admin, MaintenanceRequestAlertNotification::class);
    }

    public function test_lifecycle_widget_renders_and_reflects_open_alerts(): void
    {
        $admin = $this->adminUser();
        $this->makeRequest([
            'status' => MaintenanceRequestStatus::RECEIVED,
            'acknowledged_at' => null,
            'created_at' => now()->subHours(30),
        ]);
        $this->artisan('fieldops:check-request-alerts')->assertSuccessful();

        $this->actingAs($admin);
        Livewire::test(MaintenanceRequestLifecycleWidget::class)->assertSuccessful();
    }

    public function test_list_page_renders_with_the_lifecycle_widget(): void
    {
        $admin = $this->adminUser();
        $this->actingAs($admin);

        Livewire::test(ListMaintenanceRequests::class)->assertSuccessful();
    }

    private function adminUser(): User
    {
        $user = UserFactory::new()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function makeRequest(array $overrides = []): FoMaintenanceRequest
    {
        $client = FoClient::factory()->create();
        $board = ElectricalBoard::factory()->create();
        $createdAt = $overrides['created_at'] ?? null;
        unset($overrides['created_at']);

        $request = FoMaintenanceRequest::query()->create(array_merge([
            'client_id' => $client->id,
            'status' => MaintenanceRequestStatus::RECEIVED,
            'description' => 'Alert fixture request',
            'maintainable_type' => ElectricalBoard::class,
            'maintainable_id' => $board->id,
        ], $overrides));

        // created_at isn't mass-assignable (Eloquent convention); force it so
        // tests can simulate requests submitted hours/days in the past.
        if ($createdAt !== null) {
            $request->forceFill(['created_at' => $createdAt])->save();
        }

        return $request;
    }
}
