<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Services\ClientContactInvitationService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * F1/P2 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Client contacts are the one remaining real user-creation path outside
 * Modules\Core (invited from a client's user-management tab, CLA-268). No
 * dedicated test file existed for ClientContactInvitationService before
 * this ticket — scoped here to the organization assignment this ticket
 * adds, not a full authorization/notification suite for the invite flow.
 */
final class ClientContactInvitationOrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invited_client_contact_is_assigned_to_the_claesen_organization(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $actor = User::factory()->create();
        $actor->assignRole('super_admin');

        $client = FoClient::factory()->create();

        $service = app(ClientContactInvitationService::class);
        $invited = $service->invite($client, $actor, [
            'name' => 'QA Contact',
            'email' => 'qa.contact@example.test',
        ]);

        $this->assertSame(Organization::claesenId(), $invited->organization_id);
    }
}
