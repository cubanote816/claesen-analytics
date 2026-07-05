<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;
use Modules\Performance\Models\Mirror\MirrorRelationDelivery;
use Tests\TestCase;

class SyncComplexesFromRelationDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status'  => 'OK',
                'results' => [
                    ['geometry' => ['location' => ['lat' => 50.9, 'lng' => 5.5]]],
                ],
            ]),
        ]);
    }

    private function makeDelivery(array $overrides = []): MirrorRelationDelivery
    {
        return MirrorRelationDelivery::create(array_merge([
            'relation_id' => 700,
            'seq_nr'      => 1,
            'name'        => 'Sporthal De Alk',
            'street'      => 'Koutermanstraat 2',
            'city'        => 'Alken',
            'zipcode'     => 'BE3570',
            'fl_active'   => true,
        ], $overrides));
    }

    public function test_imports_delivery_as_complex_linked_to_its_client(): void
    {
        FoClient::factory()->create(['relation_id' => 700, 'name' => 'Gemeentebestuur Alken']);
        $this->makeDelivery();

        $this->artisan('fieldops:sync-complexes-from-relation-deliveries')->assertExitCode(0);

        $client = FoClient::where('relation_id', 700)->first();

        $this->assertDatabaseHas('fo_complexes', [
            'relation_id'     => 700,
            'delivery_seq_nr' => 1,
            'client_id'       => $client->id,
            'name'            => 'Sporthal De Alk',
            'city'            => 'Alken',
            'lat'             => 50.9,
            'lng'             => 5.5,
        ]);
    }

    public function test_skips_delivery_whose_client_was_never_synced(): void
    {
        $this->makeDelivery(['relation_id' => 701]);

        $this->artisan('fieldops:sync-complexes-from-relation-deliveries')->assertExitCode(0);

        $this->assertDatabaseMissing('fo_complexes', ['relation_id' => 701]);
    }

    public function test_skips_relation_id_zero(): void
    {
        FoClient::factory()->create(['relation_id' => 0]);
        $this->makeDelivery(['relation_id' => 0]);

        $this->artisan('fieldops:sync-complexes-from-relation-deliveries')->assertExitCode(0);

        $this->assertDatabaseMissing('fo_complexes', ['relation_id' => 0]);
    }

    public function test_rerunning_is_idempotent_and_does_not_overwrite_manually_pinned_coordinates(): void
    {
        FoClient::factory()->create(['relation_id' => 700]);
        $this->makeDelivery();

        $this->artisan('fieldops:sync-complexes-from-relation-deliveries');

        $complex = Complex::where('relation_id', 700)->where('delivery_seq_nr', 1)->first();
        $complex->update(['lat' => 51.1, 'lng' => 5.2]);

        $this->artisan('fieldops:sync-complexes-from-relation-deliveries')->assertExitCode(0);

        $this->assertEquals(1, Complex::where('relation_id', 700)->count());
        $this->assertDatabaseHas('fo_complexes', [
            'relation_id' => 700,
            'lat'         => 51.1,
            'lng'         => 5.2,
        ]);
    }

    public function test_geocodes_only_once_not_on_every_rerun(): void
    {
        FoClient::factory()->create(['relation_id' => 700]);
        $this->makeDelivery();

        $this->artisan('fieldops:sync-complexes-from-relation-deliveries');
        $this->artisan('fieldops:sync-complexes-from-relation-deliveries');

        Http::assertSentCount(1);
    }
}
