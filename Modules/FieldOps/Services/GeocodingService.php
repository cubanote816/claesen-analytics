<?php

namespace Modules\FieldOps\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingService
{
    private const ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

    /**
     * Resolve a free-form Belgian address to [lat, lng] via Google Geocoding API.
     * Returns null on any non-OK status (ZERO_RESULTS, missing/invalid key,
     * network error) — callers must treat that as "leave coordinates unset",
     * never as a fatal error for the rest of a bulk sync.
     */
    public function geocode(?string $street, ?string $city, ?string $zipcode): ?array
    {
        $key = config('services.google_geocoding.key');

        $address = trim(implode(', ', array_filter([$street, $zipcode, $city, 'Belgium'])));

        if (!$key || $address === 'Belgium') {
            return null;
        }

        $response = Http::get(self::ENDPOINT, [
            'address' => $address,
            'key'     => $key,
        ]);

        $status = $response->json('status');

        if ($status !== 'OK') {
            Log::warning('GeocodingService: could not resolve address', [
                'address' => $address,
                'status'  => $status,
            ]);

            return null;
        }

        $location = $response->json('results.0.geometry.location');

        if (!isset($location['lat'], $location['lng'])) {
            return null;
        }

        return ['lat' => $location['lat'], 'lng' => $location['lng']];
    }
}
