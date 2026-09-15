<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Modules\Prospects\Services\LeadService;
use Tests\TestCase;

class LeadServiceOrphanTest extends TestCase
{
    use RefreshDatabase;

    public function test_persist_contact_lead_succeeds_for_a_brand_new_email(): void
    {
        $prospect = (new LeadService)->persistContactLead([
            'name' => 'New Lead',
            'email' => 'new-lead-'.uniqid().'@example.test',
            'message' => 'Hello',
        ]);

        $this->assertNotNull($prospect->id);
        $this->assertSame('Overige', $prospect->region->name);
    }

    public function test_unique_violation_does_not_leave_orphan_prospect(): void
    {
        $email = 'race-'.uniqid().'@example.test';
        $regionId = Region::firstOrCreate(['name' => 'Overige'], ['slug' => 'overige'])->id;

        // A same-connection collision: while our own Prospect insert is still
        // inside the nested savepoint, a second Prospect+Location pair grabs
        // the same email. Our own later location insert then collides with it.
        // Because both writes happened after the savepoint began, the ensuing
        // ROLLBACK TO SAVEPOINT must undo both — proving no orphan Prospect can
        // survive a unique-constraint failure inside this transaction scope.
        Prospect::created(function (Prospect $prospect) use ($email, $regionId): void {
            static $raced = false;
            if ($raced) {
                return;
            }
            $raced = true;

            $racingProspect = Prospect::create([
                'name' => 'Racing Lead',
                'type' => 'lead',
                'channel' => 'website_contact',
                'region_id' => $regionId,
            ]);
            ProspectLocation::create([
                'prospect_id' => $racingProspect->id,
                'contact_type' => 'primary',
                'contact_name' => 'Racing Lead',
                'email' => $email,
            ]);
        });

        $thrown = null;
        try {
            (new LeadService)->persistContactLead([
                'name' => 'Original Lead',
                'email' => $email,
                'message' => 'Hello',
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'A same-transaction collision that gets rolled back must propagate, not silently commit an orphan.');
        $this->assertSame(0, Prospect::query()->count(), 'No orphan Prospect row should remain after the savepoint rollback.');
    }
}
