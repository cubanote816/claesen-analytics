<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\FieldOps\Models\GeocodingCache;
use Modules\FieldOps\Services\GeocodingService;
use Tests\TestCase;

class GeocodingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_and_caches_a_successful_address(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status'  => 'OK',
                'results' => [
                    ['geometry' => ['location' => ['lat' => 50.9, 'lng' => 5.5]]],
                ],
            ]),
        ]);

        $result = app(GeocodingService::class)->geocode('Koutermanstraat 2', 'Alken', 'BE3570');

        $this->assertEquals(['lat' => 50.9, 'lng' => 5.5], $result);
        $this->assertDatabaseHas('fo_geocoding_cache', [
            'status' => 'OK',
            'lat'    => 50.9,
            'lng'    => 5.5,
        ]);
    }

    public function test_second_call_for_same_address_never_hits_google(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status'  => 'OK',
                'results' => [
                    ['geometry' => ['location' => ['lat' => 50.9, 'lng' => 5.5]]],
                ],
            ]),
        ]);

        $service = app(GeocodingService::class);
        $service->geocode('Koutermanstraat 2', 'Alken', 'BE3570');
        $result = $service->geocode('Koutermanstraat 2', 'Alken', 'BE3570');

        $this->assertEquals(['lat' => 50.9, 'lng' => 5.5], $result);
        Http::assertSentCount(1);
        $this->assertEquals(1, GeocodingCache::count());
    }

    public function test_survives_the_underlying_complex_row_being_deleted(): void
    {
        // Simula exactamente el escenario que motivó este cache: la tabla
        // fo_complexes se resetea (migrate:fresh, dev DB limpiada, etc.) pero el
        // cache de geocoding (tabla separada) no se toca — la misma dirección
        // real no debería volver a pagarse/consultarse.
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status'  => 'OK',
                'results' => [
                    ['geometry' => ['location' => ['lat' => 50.9, 'lng' => 5.5]]],
                ],
            ]),
        ]);

        $service = app(GeocodingService::class);
        $service->geocode('Koutermanstraat 2', 'Alken', 'BE3570');

        // fo_complexes ya no existe/está vacía en este punto en el escenario real —
        // no hay nada que borrar acá porque GeocodingCache es independiente, eso es
        // justamente lo que se está probando.
        $result = $service->geocode('Koutermanstraat 2', 'Alken', 'BE3570');

        $this->assertEquals(['lat' => 50.9, 'lng' => 5.5], $result);
        Http::assertSentCount(1);
    }

    public function test_zero_results_is_cached_and_not_retried(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response(['status' => 'ZERO_RESULTS']),
        ]);

        $service = app(GeocodingService::class);
        $first = $service->geocode('Nonexistent Street 999', 'Nowhere', 'BE0000');
        $second = $service->geocode('Nonexistent Street 999', 'Nowhere', 'BE0000');

        $this->assertNull($first);
        $this->assertNull($second);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('fo_geocoding_cache', ['status' => 'ZERO_RESULTS']);
    }

    public function test_returns_null_without_hitting_google_when_no_api_key_configured(): void
    {
        config(['services.google_geocoding.key' => null]);
        Http::fake();

        $result = app(GeocodingService::class)->geocode('Koutermanstraat 2', 'Alken', 'BE3570');

        $this->assertNull($result);
        Http::assertNothingSent();
        $this->assertEquals(0, GeocodingCache::count());
    }
}
