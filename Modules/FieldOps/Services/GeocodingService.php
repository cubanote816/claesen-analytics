<?php

namespace Modules\FieldOps\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\FieldOps\Models\GeocodingCache;

class GeocodingService
{
    private const ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * Resolve a free-form Belgian address to [lat, lng] via Google Geocoding API.
     * Returns null on any non-OK status (ZERO_RESULTS, missing/invalid key,
     * network error) — callers must treat that as "leave coordinates unset",
     * never as a fatal error for the rest of a bulk sync.
     *
     * Every result (successful or not) is cached in fo_geocoding_cache, keyed by
     * a hash of the normalized address, independent of any Complex row. This
     * table is never touched by a Complex reset/reseed — a real-world address
     * already resolved once is never billed/queried again, and an address Google
     * could never resolve (ZERO_RESULTS) isn't retried forever on every sync run.
     */
    public function geocode(?string $street, ?string $city, ?string $zipcode): ?array
    {
        $key = config('services.google_geocoding.key');

        $address = trim(implode(', ', array_filter([$street, $zipcode, $city, 'Belgium'])));

        if (!$key || $address === 'Belgium') {
            return null;
        }

        $hash = sha1(mb_strtolower($address));

        $cached = GeocodingCache::where('address_hash', $hash)->first();

        if ($cached) {
            return $cached->status === 'OK'
                ? ['lat' => $cached->lat, 'lng' => $cached->lng]
                : null;
        }

        $response = Http::get(self::ENDPOINT, [
            'address' => $address,
            'key'     => $key,
        ]);

        $status = $response->json('status') ?? 'ERROR';
        $location = $status === 'OK' ? $response->json('results.0.geometry.location') : null;
        $resolvedOk = isset($location['lat'], $location['lng']);

        GeocodingCache::create([
            'address_hash' => $hash,
            'address'      => $address,
            'lat'          => $resolvedOk ? $location['lat'] : null,
            'lng'          => $resolvedOk ? $location['lng'] : null,
            'status'       => $resolvedOk ? 'OK' : $status,
        ]);

        if (!$resolvedOk) {
            Log::warning('GeocodingService: could not resolve address', [
                'address' => $address,
                'status'  => $status,
            ]);

            return null;
        }

        return ['lat' => $location['lat'], 'lng' => $location['lng']];
    }
}
