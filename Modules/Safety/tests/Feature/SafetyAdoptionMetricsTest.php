<?php

namespace Modules\Safety\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Modules\Core\Models\User;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Question;
use Modules\Safety\Models\SafetyAdoptionEvent;
use Modules\Safety\Models\SafetyAdoptionDailyRollup;
use Illuminate\Support\Facades\Artisan;
use Modules\Safety\Services\SafetyAdoptionMetricsService;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

class SafetyAdoptionMetricsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure roles exist
        Role::firstOrCreate(['name' => 'project_manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_aggregate_command_processes_metrics_and_separates_inspections_and_incidents()
    {
        $user = User::factory()->create();
        
        $yesterday = Carbon::yesterday();

        // Create an inspection completed event
        \Illuminate\Support\Facades\DB::table('safety_adoption_events')->insert([
            'user_id' => $user->id,
            'event_type' => 'inspection_completed',
            'project_id' => 'P-TEST',
            'metadata' => json_encode(['type' => 'inspection', 'inspection_id' => 1]),
            'created_at' => $yesterday->copy()->setHour(10),
            'updated_at' => $yesterday->copy()->setHour(10),
        ]);

        // Create an incident reported event
        \Illuminate\Support\Facades\DB::table('safety_adoption_events')->insert([
            'user_id' => $user->id,
            'event_type' => 'inspection_completed',
            'project_id' => 'P-TEST',
            'metadata' => json_encode(['type' => 'incident', 'inspection_id' => 2]),
            'created_at' => $yesterday->copy()->setHour(11),
            'updated_at' => $yesterday->copy()->setHour(11),
        ]);

        Artisan::call('safety:aggregate-adoption', ['--date' => $yesterday->toDateString()]);

        $inspectionsCount = SafetyAdoptionDailyRollup::where('date', $yesterday->toDateString())
            ->where('metric_name', 'inspections_completed')
            ->value('value');
            
        $incidentsCount = SafetyAdoptionDailyRollup::where('date', $yesterday->toDateString())
            ->where('metric_name', 'incidents_reported')
            ->value('value');

        $this->assertEquals(1, $inspectionsCount);
        $this->assertEquals(1, $incidentsCount);
    }

    public function test_purge_removes_events_older_than_90_days()
    {
        $user = User::factory()->create();
        
        \Illuminate\Support\Facades\DB::table('safety_adoption_events')->insert([
            'user_id' => $user->id,
            'event_type' => 'inspection_completed',
            'project_id' => 'P-TEST',
            'metadata' => json_encode(['type' => 'inspection', 'inspection_id' => 1]),
            'created_at' => Carbon::now()->subDays(91),
            'updated_at' => Carbon::now()->subDays(91),
        ]);

        \Illuminate\Support\Facades\DB::table('safety_adoption_events')->insert([
            'user_id' => $user->id,
            'event_type' => 'inspection_completed',
            'project_id' => 'P-TEST',
            'metadata' => json_encode(['type' => 'inspection', 'inspection_id' => 2]),
            'created_at' => Carbon::now()->subDays(89),
            'updated_at' => Carbon::now()->subDays(89),
        ]);

        $service = new SafetyAdoptionMetricsService();
        $deleted = $service->purgeOldEvents(90);

        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, SafetyAdoptionEvent::count());
    }

    public function test_idempotency_conflict_creates_friction_event_and_does_not_double_count()
    {
        $user = User::factory()->create();
        $user->assignRole('project_manager');
        $token = $user->createToken('test', ['role:safety-access'])->plainTextToken;

        $checklist = Checklist::factory()->create(['type' => 'inspection']);
        $question = Question::factory()->create(['checklist_id' => $checklist->id]);

        $idempotencyKey = (string) \Illuminate\Support\Str::uuid();

        // Satisfy exists:employees,id validation
        $this->createTestEmployeeFor($user);

        $payload1 = [
            'checklist_id' => $checklist->id,
            'type' => 'inspection',
            'project_id' => 'P-TEST',
            'idempotency_key' => $idempotencyKey,
            'present_workers' => [$user->id],
            'answers' => [
                ['question_id' => $question->id, 'value' => 'YES']
            ]
        ];

        // 1. First request should succeed and create 'inspection_completed'
        $response1 = $this->withToken($token)->postJson('/api/v1/safety/inspections', $payload1);
        $response1->assertStatus(201);
        
        $this->assertDatabaseHas('safety_adoption_events', [
            'event_type' => 'inspection_completed',
            'user_id' => $user->id,
        ]);
        
        $initialCompletedEvents = SafetyAdoptionEvent::where('event_type', 'inspection_completed')->count();
        $this->assertEquals(1, $initialCompletedEvents);

        // 2. Exact same payload with same idempotency key -> Returns 200, no new completed event, no conflict event
        $response2 = $this->withToken($token)->postJson('/api/v1/safety/inspections', $payload1);
        $response2->assertStatus(200);

        // Verify it didn't create another completion event or a friction event
        $this->assertEquals(1, SafetyAdoptionEvent::where('event_type', 'inspection_completed')->count());
        $this->assertEquals(0, SafetyAdoptionEvent::where('event_type', 'inspection_payload_conflict')->count());

        // 3. Different payload with same idempotency key -> Returns 409, creates friction event
        $payload3 = $payload1;
        $payload3['answers'][0]['value'] = 'NO'; // Change payload to force conflict

        $response3 = $this->withToken($token)->postJson('/api/v1/safety/inspections', $payload3);
        $response3->assertStatus(409);

        // Verify conflict event was recorded
        $this->assertDatabaseHas('safety_adoption_events', [
            'event_type' => 'inspection_payload_conflict',
            'user_id' => $user->id,
        ]);

        // Verify completion count is still 1
        $this->assertEquals(1, SafetyAdoptionEvent::where('event_type', 'inspection_completed')->count());
    }

    private function createTestEmployeeFor(User $user): \Modules\Cafca\Models\Employee
    {
        return \Modules\Cafca\Models\Employee::create([
            'id' => (string) $user->id,
            'name' => $user->name,
            'fl_active' => true,
        ]);
    }
}
