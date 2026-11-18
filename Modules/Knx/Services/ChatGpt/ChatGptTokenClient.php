<?php

declare(strict_types=1);

namespace Modules\Knx\Services\ChatGpt;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The token endpoint: authorization-code exchange and refresh. A public client sends
 * no secret; a confidential one sends it ONLY as HTTP Basic (form-encoded components),
 * never in the body, and never silently falls back to public.
 */
final class ChatGptTokenClient
{
    /** Refresh errors after which only a new sign-in helps. */
    private const TERMINAL_REFRESH_ERRORS = [
        'invalid_grant',
        'invalid_refresh_token',
        'refresh_token_expired',
        'refresh_token_invalidated',
        'refresh_token_reused',
        'token_expired',
    ];

    public function __construct(
        private readonly ChatGptSettings $settings,
        private readonly ChatGptDiscovery $discovery,
    ) {}

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scopes: list<string>, id_token: ?string}
     */
    public function exchange(string $code, string $verifier): array
    {
        $response = $this->post([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->settings->redirectUri(),
            'client_id' => $this->settings->clientId(),
            'code_verifier' => $verifier,
            'resource' => $this->settings->resource(),
        ], 'token_exchange_failed');

        return $this->tokens($response, 'token_exchange_failed');
    }

    /**
     * Refresh rotates: the response carries a NEW refresh token that replaces the old one.
     * A terminal error is `needs_sign_in`; anything else is temporary (`refresh_failed`).
     *
     * @return array{access_token: string, refresh_token: ?string, expires_in: int, scopes: ?list<string>, id_token: ?string}
     */
    public function refresh(string $refreshToken, string $clientId): array
    {
        $response = $this->post([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'resource' => $this->settings->resource(),
        ], 'refresh_failed');

        if (! $response->successful()) {
            $terminal = $response->status() >= 400 && $response->status() < 500
                && in_array($this->errorCode($response), self::TERMINAL_REFRESH_ERRORS, true);

            throw new ChatGptFailed($terminal ? 'needs_sign_in' : 'refresh_failed', 'The refresh was rejected.');
        }

        return $this->tokens($response, 'refresh_failed', requireScope: false);
    }

    /** @param  array<string, string>  $form */
    private function post(array $form, string $failure): Response
    {
        $endpoint = $this->discovery->document()['token_endpoint'];

        $request = Http::asForm()->acceptJson()->timeout(20);
        $request = $this->authenticate($request);

        try {
            $response = $request->post($endpoint, $form);
        } catch (ConnectionException) {
            throw new ChatGptFailed($failure, 'The token endpoint could not be reached.');
        }

        if ($failure === 'token_exchange_failed' && ! $response->successful()) {
            throw new ChatGptFailed($failure, 'The token endpoint rejected the code ('.($this->errorCode($response) ?? $response->status()).').');
        }

        return $response;
    }

    private function authenticate(PendingRequest $request): PendingRequest
    {
        if ($this->settings->tokenAuth() !== ChatGptSettings::AUTH_BASIC) {
            return $request;
        }

        return $request->withBasicAuth(
            urlencode($this->settings->clientId()),
            urlencode($this->settings->clientSecret()),
        );
    }

    /** @return array{access_token: string, refresh_token: ?string, expires_in: int, scopes: ?list<string>, id_token: ?string} */
    private function tokens(Response $response, string $failure, bool $requireScope = true): array
    {
        $body = $response->json();
        $access = is_array($body) ? ($body['access_token'] ?? null) : null;

        if (! $response->successful() || ! is_string($access) || $access === '') {
            throw new ChatGptFailed($failure, 'The token response is unusable.');
        }

        $scope = $body['scope'] ?? null;
        $scopes = is_string($scope) ? array_values(preg_split('/\s+/', trim($scope), -1, PREG_SPLIT_NO_EMPTY)) : null;

        return [
            'access_token' => $access,
            'refresh_token' => is_string($body['refresh_token'] ?? null) && $body['refresh_token'] !== '' ? $body['refresh_token'] : null,
            'expires_in' => is_numeric($body['expires_in'] ?? null) ? (int) $body['expires_in'] : 3600,
            // No `scope` on a code exchange means nothing was proven granted.
            'scopes' => $scopes ?? ($requireScope ? [] : null),
            'id_token' => is_string($body['id_token'] ?? null) && $body['id_token'] !== '' ? $body['id_token'] : null,
        ];
    }

    private function errorCode(Response $response): ?string
    {
        $error = $response->json('error');

        if (is_array($error)) {
            $error = $error['code'] ?? $error['type'] ?? null;
        }

        return is_string($error) ? $error : null;
    }
}
