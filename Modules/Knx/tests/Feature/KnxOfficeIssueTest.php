<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxBoard;
use Modules\Knx\Models\KnxConflict;
use Modules\Knx\Models\KnxDevice;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxProject;
use Modules\Knx\Models\KnxProjectRoom;
use Modules\Knx\Services\KnxNoticeMailer;
use Tests\TestCase;

/**
 * KNX-56 — the office files an incident itself.
 *
 * The field already reports incidents (V11.d). The case this ticket names is the
 * client's call after handover: the office person opens the incident, links it to the
 * real room/device/board, and a `malfunction` gets its own lifecycle and its immediate
 * notice.
 */
final class KnxOfficeIssueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        Storage::fake('local');

        $this->actingAs($this->officeUser(), 'sanctum');
    }

    private function officeUser(): User
    {
        return User::query()->where('email', 'lien.smet@electrobertels.be')->sole();
    }

    private function project(string $code = 'C1618'): KnxProject
    {
        return KnxProject::query()->where('code', $code)->sole();
    }

    private function device(): KnxDevice
    {
        return KnxDevice::query()
            ->where('project_id', $this->project()->getKey())
            ->where('address', '1.1.111')
            ->sole();
    }

    private function room(): KnxProjectRoom
    {
        return $this->device()->room;
    }

    private function board(): KnxBoard
    {
        return KnxBoard::query()
            ->where('project_id', $this->project()->getKey())
            ->orderBy('id')
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'damaged',
            'roomId' => $this->room()->getKey(),
            'deviceId' => $this->device()->getKey(),
            'boardId' => $this->board()->code,
            'moduleSlot' => 3,
            'channel' => 'kanaal 2',
            'note' => 'Klant belde na de oplevering.',
        ], $overrides);
    }

    public function test_the_office_files_an_incident_the_conflict_list_shows(): void
    {
        $response = $this->postJson('/api/v1/knx/projects/C1618/issues', $this->payload())->assertCreated();

        $payload = $response->json();

        // The contract's `Conflict`: the office gets the row it must triage back.
        $this->assertSame('ets', $payload['lifecycle']);
        $this->assertSame('damaged', $payload['type']);
        $this->assertSame('info', $payload['severity']);
        $this->assertSame('open', $payload['status']);
        $this->assertSame('L. Smet', $payload['reportedBy']);
        $this->assertNull($payload['symptom']);

        $conflict = KnxConflict::query()->where('id', $payload['id'])->sole();

        $this->assertSame($this->device()->getKey(), $conflict->device_id);
        $this->assertSame('1.1.111', $conflict->address);
        $this->assertSame(
            $this->room()->name.' · 1.1.111 · '.$this->board()->code.' · slot 3 · kanaal 2',
            $conflict->device_field,
        );
        // The report itself is the first line, with its author (KNX-56).
        $this->assertSame('L. Smet', $conflict->logs->first()->by);

        $notice = KnxNotification::query()->where('kind', KnxNotification::KIND_ISSUE_REPORTED)->sole();
        $this->assertSame('conflict', $notice->target_type);
        $this->assertSame((string) $conflict->getKey(), $notice->target_id);
    }

    public function test_the_office_files_a_malfunction_and_it_warns_immediately(): void
    {
        $response = $this->postJson('/api/v1/knx/projects/C1618/issues', $this->payload([
            'kind' => 'malfunction',
            'symptom' => 'Gaat niet aan als de aanwezigheidsmelder aanslaat.',
        ]))->assertCreated();

        $this->assertSame('malfunction', $response->json('lifecycle'));
        $this->assertSame('malfunction', $response->json('type'));
        $this->assertSame('warning', $response->json('severity'));
        $this->assertSame('Gaat niet aan als de aanwezigheidsmelder aanslaat.', $response->json('symptom'));

        $notice = KnxNotification::query()->where('kind', KnxNotification::KIND_MALFUNCTION_REPORTED)->sole();

        $this->assertSame('conflict', $notice->target_type);
        // The ticket's requirement: it always warns as it happens, not in a digest.
        $this->assertTrue(app(KnxNoticeMailer::class)->isImmediate($notice));
    }

    public function test_a_malfunction_without_a_symptom_is_a_field_error(): void
    {
        $this->postJson('/api/v1/knx/projects/C1618/issues', $this->payload(['kind' => 'malfunction']))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['symptom']]);

        $this->assertSame(0, KnxConflict::query()->where('type', 'malfunction')->count());
    }

    public function test_an_id_from_another_project_is_a_field_error(): void
    {
        // A device of another project: anchoring the incident there would be lying.
        $other = KnxDevice::query()->where('project_id', '!=', $this->project()->getKey())->first();

        $this->postJson('/api/v1/knx/projects/C1618/issues', $this->payload(['deviceId' => $other->getKey()]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['deviceId']]);

        $this->postJson('/api/v1/knx/projects/C1618/issues', $this->payload(['roomId' => 999999]))
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['roomId']]);
    }

    public function test_the_office_incident_walks_the_malfunction_cycle(): void
    {
        $id = $this->postJson('/api/v1/knx/projects/C1618/issues', $this->payload([
            'kind' => 'malfunction',
            'symptom' => 'Schakelt de verkeerde kring.',
        ]))->assertCreated()->json('id');

        $this->patchJson("/api/v1/knx/conflicts/{$id}", ['status' => 'diagnosing'])
            ->assertOk()
            ->assertJsonPath('status', 'diagnosing');
    }

    public function test_the_office_route_is_office_only_and_needs_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/knx/projects/C1618/issues', $this->payload())
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        $this->actingAs(
            User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(),
            'sanctum',
        );

        // A technician's token is not an office identity (the guard answers 401).
        $this->postJson('/api/v1/knx/projects/C1618/issues', $this->payload())->assertUnauthorized();
    }

    public function test_an_unknown_project_is_a_404(): void
    {
        $this->postJson('/api/v1/knx/projects/NOPE/issues', $this->payload())->assertNotFound();
    }
}
