<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\FieldOps\Models\FoClient;
use Modules\Performance\Models\Mirror\MirrorRelation;
use Tests\TestCase;

class SyncClientsFromRelationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeRelation(array $overrides = []): MirrorRelation
    {
        return MirrorRelation::create(array_merge([
            'id'           => 500,
            'name'         => 'Stadspark Antwerpen',
            'city'         => 'Antwerpen',
            'street'       => 'Grote Steenweg 1',
            'country'      => 'BE',
            'language'     => 'nl',
            'email'        => 'info@stadspark.be',
            'phone'        => '03/123 45 67',
            'tp_customer'  => true,
        ], $overrides));
    }

    public function test_imports_customer_relations_as_fo_clients(): void
    {
        $this->makeRelation();

        $this->artisan('fieldops:sync-clients-from-relations')->assertExitCode(0);

        $this->assertDatabaseHas('fo_clients', [
            'relation_id' => 500,
            'name'        => 'Stadspark Antwerpen',
            'city'        => 'Antwerpen',
            'street'      => 'Grote Steenweg 1',
            'phone'       => '03/123 45 67',
            'email'       => 'info@stadspark.be',
            'language'    => 'nl',
        ]);
    }

    public function test_skips_relations_that_are_not_customers(): void
    {
        $this->makeRelation(['id' => 501, 'tp_customer' => false]);

        $this->artisan('fieldops:sync-clients-from-relations')->assertExitCode(0);

        $this->assertDatabaseMissing('fo_clients', ['relation_id' => 501]);
    }

    public function test_rerunning_is_idempotent(): void
    {
        $this->makeRelation();

        $this->artisan('fieldops:sync-clients-from-relations')->assertExitCode(0);
        $this->artisan('fieldops:sync-clients-from-relations')->assertExitCode(0);

        $this->assertEquals(1, FoClient::where('relation_id', 500)->count());
    }

    public function test_restores_a_soft_deleted_client_instead_of_colliding_on_relation_id(): void
    {
        $this->makeRelation();
        $this->artisan('fieldops:sync-clients-from-relations');

        FoClient::where('relation_id', 500)->first()->delete();
        $this->assertSoftDeleted('fo_clients', ['relation_id' => 500]);

        $this->artisan('fieldops:sync-clients-from-relations')->assertExitCode(0);

        $this->assertDatabaseHas('fo_clients', ['relation_id' => 500, 'deleted_at' => null]);
    }
}
