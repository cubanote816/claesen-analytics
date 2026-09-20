<?php

declare(strict_types=1);

namespace Modules\Website\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * F4/CLA-478 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Source y UTM se normalizan en servidor; no se confía en source
 * arbitrario" — before this, both intake controllers either accepted the
 * client's own free-text `source` field verbatim (ConsultationController)
 * or used the raw Referer header string unfiltered
 * (ContactController::store()'s `$request->header('Referer') ?? '...'`).
 * Either lets a caller write an arbitrary string straight into a stored
 * lead's `source` column.
 *
 * This never trusts a client-submitted `source` value. It only ever
 * derives one from: (1) an explicit utm_source query param, whitelisted to
 * a safe charset and length, or (2) the Referer header's own hostname
 * (never its full, attacker-controlled path/query), or (3) the literal
 * fallback 'website'.
 */
class LeadSourceNormalizer
{
    private const MAX_LENGTH = 100;

    public function resolveSource(Request $request): string
    {
        $utmSource = $this->sanitize($request->query('utm_source'));

        if ($utmSource !== null) {
            return $utmSource;
        }

        $refererHost = $this->refererHost($request);

        return $refererHost ?? 'website';
    }

    /**
     * utm_medium/utm_campaign are stored as-is (sanitized) inside
     * ConsultationRequest::custom_fields['utm'] — no dedicated columns
     * exist for them and adding one is out of this ticket's scope; JSON
     * is this table's own established extensibility point.
     */
    public function resolveUtm(Request $request): array
    {
        return array_filter([
            'source' => $this->sanitize($request->query('utm_source')),
            'medium' => $this->sanitize($request->query('utm_medium')),
            'campaign' => $this->sanitize($request->query('utm_campaign')),
        ], fn ($value) => $value !== null);
    }

    private function refererHost(Request $request): ?string
    {
        $referer = $request->header('Referer');

        if (! $referer) {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return $host ? $this->sanitize($host) : null;
    }

    private function sanitize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Alphanumerics, dash, underscore, dot only — a UTM value or a
        // hostname never legitimately needs anything else, and this
        // forecloses any injection/formatting surprise downstream.
        $clean = preg_replace('/[^A-Za-z0-9\-_.]/', '', $value);

        if ($clean === '' || $clean === null) {
            return null;
        }

        return Str::limit($clean, self::MAX_LENGTH, '');
    }
}
