<?php

namespace Modules\FieldOps\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\FieldOps\Models\GeocodingCache;

class GeocodingService
{
    private const ENDPOINT = 'https://maps.googleapis.com/maps/api/geocode/json';

    // Estados de Google que son un veredicto real y definitivo sobre la
    // dirección en sí — seguros de cachear para siempre. Cualquier otro status
    // (REQUEST_DENIED, OVER_QUERY_LIMIT, OVER_DAILY_LIMIT, UNKNOWN_ERROR, o una
    // excepción de red) es un problema transitorio/de infraestructura (key mal
    // configurada, IP no autorizada, cuota agotada, etc.) — cachearlo sería
    // "recordar" el fallo para siempre y nunca más reintentar esa dirección aunque
    // se arregle la causa real.
    private const FINAL_STATUSES = ['OK', 'ZERO_RESULTS'];

    /**
     * Resolve a free-form Belgian address to [lat, lng] via Google Geocoding API.
     * Returns null on any non-OK status (ZERO_RESULTS, missing/invalid key,
     * network error) — callers must treat that as "leave coordinates unset",
     * never as a fatal error for the rest of a bulk sync.
     *
     * Every *final* result (successful or genuinely ZERO_RESULTS) is cached in
     * fo_geocoding_cache, keyed by a hash of the normalized address, independent
     * of any Complex row. This table is never touched by a Complex reset/reseed —
     * a real-world address already resolved once is never billed/queried again.
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
        $finalStatus = $resolvedOk ? 'OK' : $status;

        if (in_array($finalStatus, self::FINAL_STATUSES, true)) {
            GeocodingCache::create([
                'address_hash' => $hash,
                'address'      => $address,
                'lat'          => $resolvedOk ? $location['lat'] : null,
                'lng'          => $resolvedOk ? $location['lng'] : null,
                'status'       => $finalStatus,
            ]);
        }

        if (!$resolvedOk) {
            Log::warning('GeocodingService: could not resolve address', [
                'address' => $address,
                'status'  => $status,
                'cached'  => in_array($finalStatus, self::FINAL_STATUSES, true),
            ]);

            return null;
        }

        return ['lat' => $location['lat'], 'lng' => $location['lng']];
    }
}
