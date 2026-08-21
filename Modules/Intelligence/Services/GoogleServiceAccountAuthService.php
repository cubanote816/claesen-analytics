<?php

namespace Modules\Intelligence\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CLA-407 — this Google Cloud project's organization policy blocks
 * creating or using a plain API key (`?key=...`) for the Generative
 * Language API (Gemini): any key with Gemini access must be bound to a
 * service account. This exchanges a dedicated service account's JSON
 * credentials for a short-lived OAuth2 access token (JWT-bearer flow,
 * RFC 7523) so callers authenticate via `Authorization: Bearer` instead.
 *
 * Single shared implementation — every place in the app that calls Gemini
 * (GeminiService plus the handful of services/jobs that built their own
 * HTTP calls) goes through this instead of duplicating JWT signing.
 */
class GoogleServiceAccountAuthService
{
    // CLA-407 — the plain Generative Language API ("Gemini API for
    // developers") rejects service-account OAuth2 tokens outright ("Access
    // to Gemini API is restricted with service accounts. Use authorization
    // keys instead.", confirmed against the real API) — only Vertex AI
    // supports OAuth2/service-account auth natively, hence cloud-platform.
    private const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    private const CACHE_KEY = 'google_service_account_access_token';

    // Google tokens are valid for 3600s; refresh a bit early so a request
    // never starts with a token that expires mid-flight.
    private const CACHE_TTL_SECONDS = 3000;

    public function getAccessToken(): ?string
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): ?string {
            $path = config('services.gemini.service_account_path');

            if (empty($path) || ! is_readable($path)) {
                Log::error('GoogleServiceAccountAuthService: service account file missing or unreadable.', ['path' => $path]);

                return null;
            }

            $credentials = json_decode((string) file_get_contents($path), true);
            $clientEmail = $credentials['client_email'] ?? null;
            $privateKey = $credentials['private_key'] ?? null;
            $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';

            if (! $clientEmail || ! $privateKey) {
                Log::error('GoogleServiceAccountAuthService: service account file is missing client_email/private_key.');

                return null;
            }

            $jwt = $this->buildSignedJwt($clientEmail, $privateKey, $tokenUri);

            if ($jwt === null) {
                return null;
            }

            $response = Http::asForm()->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->failed()) {
                Log::error('GoogleServiceAccountAuthService: token exchange failed.', ['body' => $response->body()]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    private function buildSignedJwt(string $clientEmail, string $privateKey, string $tokenUri): ?string
    {
        $now = time();

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $clientEmail,
            'scope' => self::SCOPE,
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $signingInput = $this->base64UrlEncode(json_encode($header))
            .'.'.$this->base64UrlEncode(json_encode($claims));

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            Log::error('GoogleServiceAccountAuthService: failed to sign JWT with service account private key.');

            return null;
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
