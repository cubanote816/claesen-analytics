<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxPlanningAssignment;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Services\FieldTodayService;
use Tests\TestCase;

/**
 * V11.a — the field app's session and work list (CLA-609).
 *
 * The rule that matters is scope: a technician sees the projects the planning put
 * them on **today**, and nothing else. That is the contract's own requirement, and
 * it is what keeps a phone from listing the whole office's work.
 */
final class KnxFieldSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);
    }

    private function fieldUser(string $email = 'jan.van.dyck@electrobertels.be'): User
    {
        return User::query()->where('email', $email)->sole();
    }

    private function officeUser(): User
    {
        return User::query()->where('email', 'lien.smet@electrobertels.be')->sole();
    }

    /** The fixture plans Monday to Friday, so "today" needs a row of its own. */
    private function planToday(KnxEmployee $technician, string $projectCode): void
    {
        // The fixture plans Monday to Friday, so today may already have a row for
        // this technician — and (employee, date) is unique.
        KnxPlanningAssignment::updateOrCreate(
            ['employee_id' => $technician->getKey(), 'date' => now()->toDateString()],
            ['project_id' => KnxProject::query()->where('code', $projectCode)->sole()->getKey()],
        );
    }

    public function test_the_field_session_has_its_own_shape(): void
    {
        $this->actingAs($this->fieldUser(), 'sanctum');

        $payload = $this->getJson('/api/v1/knx/field/session')->assertOk()->json();

        // No role, no email: the phone shows a name on a loading screen, and the
        // office payload would invite the app to ask for office things.
        $this->assertSame(['id', 'name', 'initials', 'domain'], array_keys($payload));
        $this->assertSame('Jan Van Dyck', $payload['name']);
        $this->assertSame('JV', $payload['initials']);
        $this->assertSame('veld.electrobertels.be', $payload['domain']);
    }

    public function test_the_two_apps_do_not_accept_each_others_accounts(): void
    {
        // An office account on the field endpoint…
        $this->actingAs($this->officeUser(), 'sanctum');
        $this->getJson('/api/v1/knx/field/session')->assertUnauthorized();
        $this->getJson('/api/v1/knx/field/today')->assertUnauthorized();

        $this->app['auth']->forgetGuards();

        // …and a field account on the office endpoint.
        $this->actingAs($this->fieldUser(), 'sanctum');
        $this->getJson('/api/v1/knx/me/session')->assertUnauthorized();
        $this->getJson('/api/v1/knx/projects')->assertUnauthorized();
    }

    public function test_the_work_list_has_the_contract_shape(): void
    {
        $technician = KnxEmployee::query()->where('name', 'Jan Van Dyck')->sole();
        $this->planToday($technician, 'C1618');

        $this->actingAs($this->fieldUser(), 'sanctum');

        $jobs = $this->getJson('/api/v1/knx/field/today')->assertOk()->json();

        $this->assertNotEmpty($jobs);
        $this->assertSame(
            ['id', 'projectCode', 'projectName', 'city', 'room', 'zoneStatus', 'blockingReason', 'tasks'],
            array_keys($jobs[0]),
        );

        $job = collect($jobs)->firstWhere('projectCode', 'C1618');

        $this->assertNotNull($job);
        $this->assertSame('UV Campus · Gelijkvloers', $job['projectName']);
        $this->assertSame('Heverlee', $job['city']);

        // The zone that needs attention, not just the first one: C1618 has four and
        // only Vergaderzaal is blocked.
        $this->assertSame('Vergaderzaal', $job['room']);
        $this->assertSame('blocked', $job['zoneStatus']);
        $this->assertSame('DALI-driver niet geleverd — kan verlichting niet testen', $job['blockingReason']);

        // Tasks come from the data: C1618 has 17 of 24 devices registered and tests
        // still open.
        $this->assertSame(['Toestellen registreren', 'Verlichting testen'], $job['tasks']);
    }

    public function test_a_technician_only_sees_the_projects_they_are_on_today(): void
    {
        $jan = KnxEmployee::query()->where('name', 'Jan Van Dyck')->sole();
        $mira = KnxEmployee::query()->where('name', 'Mira Claes')->sole();

        $this->planToday($jan, 'C1618');
        $this->planToday($mira, '239870');

        $this->actingAs($this->fieldUser('jan.van.dyck@electrobertels.be'), 'sanctum');
        $janJobs = collect($this->getJson('/api/v1/knx/field/today')->json())->pluck('projectCode')->all();

        $this->assertContains('C1618', $janJobs);
        $this->assertNotContains('239870', $janJobs, 'a project another technician is on today is not mine');

        $this->app['auth']->forgetGuards();
        $this->actingAs($this->fieldUser('mira.claes@electrobertels.be'), 'sanctum');
        $miraJobs = collect($this->getJson('/api/v1/knx/field/today')->json())->pluck('projectCode')->all();

        $this->assertContains('239870', $miraJobs);
        $this->assertNotContains('C1618', $miraJobs);
    }

    public function test_a_technician_with_no_work_today_gets_an_empty_list_not_an_error(): void
    {
        // The three technicians without an account are also without an assignment
        // today in most weeks; the app must show "no work", not a failure.
        KnxPlanningAssignment::query()->whereDate('date', now()->toDateString())->delete();

        $this->actingAs($this->fieldUser(), 'sanctum');

        $this->getJson('/api/v1/knx/field/today')->assertOk()->assertJsonCount(0);
    }

    public function test_the_assignment_scope_is_a_single_answer_the_whole_field_api_can_use(): void
    {
        $jan = KnxEmployee::query()->where('name', 'Jan Van Dyck')->sole();
        $this->planToday($jan, 'C1618');

        $today = app(FieldTodayService::class);

        $this->assertTrue($today->isAssignedToday($jan, KnxProject::query()->where('code', 'C1618')->sole()));
        $this->assertFalse($today->isAssignedToday($jan, KnxProject::query()->where('code', '239870')->sole()));

        // Yesterday's assignment is not today's: the row moves out of today (moving
        // the whole week would collide with the fixture's other days).
        KnxPlanningAssignment::query()
            ->where('employee_id', $jan->getKey())
            ->whereDate('date', now()->toDateString())
            ->delete();

        $this->assertFalse($today->isAssignedToday($jan, KnxProject::query()->where('code', 'C1618')->sole()));
    }

    public function test_the_field_endpoints_require_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/knx/field/session')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
        $this->getJson('/api/v1/knx/field/today')->assertUnauthorized();
    }
}
