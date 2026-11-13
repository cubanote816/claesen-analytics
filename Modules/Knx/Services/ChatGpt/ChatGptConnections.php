<?php

declare(strict_types=1);

namespace Modules\Knx\Services\ChatGpt;

use Illuminate\Support\Facades\Cache;
use Modules\Knx\Models\KnxChatGptConnection;
use Modules\Knx\Models\KnxEmployee;

/**
 * Each employee connects THEIR OWN ChatGPT account. Sign-in (who) and plan permission
 * (may we consume the subscription) are separate: the second is true only when the token
 * response granted both plan scopes. Nothing here ever falls back to a paid API.
 */
final class ChatGptConnections
{
    public function __construct(
        private readonly ChatGptSettings $settings,
        private readonly ChatGptDiscovery $discovery,
        private readonly ChatGptTransactions $transactions,
        private readonly ChatGptIdTokenValidator $idTokens,
        private readonly ChatGptTokenClient $tokens,
    ) {}

    public function connectionFor(KnxEmployee $employee): ?KnxChatGptConnection
    {
        return KnxChatGptConnection::query()
            ->where('organization_id', $employee->organization_id)
            ->where('employee_id', $employee->getKey())
            ->first();
    }

    /** The shape of `GET /chatgpt/connection`. No token is ever part of it. */
    public function status(KnxEmployee $employee): array
    {
        $connection = $this->connectionFor($employee);
        $connected = $connection?->status === KnxChatGptConnection::STATUS_CONNECTED;

        return [
            'configured' => $this->settings->configured(),
            'missingSettings' => $this->settings->missing(),
            'connected' => $connected,
            'status' => $connection?->status ?? 'not_connected',
            'identity' => $connection === null ? null : ['email' => $connection->email, 'name' => $connection->name],
            'planPermission' => $connected && (bool) $connection->plan_permission,
            'scopes' => $connection === null ? [] : array_values($connection->scopes ?? []),
            'accessExpiresAt' => $connection?->access_expires_at?->toIso8601String(),
        ];
    }

    public function authorizationUrl(KnxEmployee $employee): string
    {
        $this->settings->assertConfigured();

        $document = $this->discovery->document();
        $transaction = $this->transactions->begin((int) $employee->getKey());

        return $document['authorization_endpoint'].'?'.http_build_query([
            'client_id' => $this->settings->clientId(),
            'redirect_uri' => $this->settings->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $this->settings->requestedScopes()),
            'resource' => $this->settings->resource(),
            'state' => $transaction['state'],
            'nonce' => $transaction['nonce'],
            'code_challenge' => $transaction['challenge'],
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The browser came back from OpenAI. Returns `['signIn' => 'ok'|'declined', 'planPermission' => bool]`.
     * Anything else throws a ChatGptFailed and leaves nothing stored.
     *
     * @return array{signIn: string, planPermission: bool}
     */
    public function complete(KnxEmployee $employee, string $state, ?string $code, ?string $error): array
    {
        $this->settings->assertConfigured();

        $transaction = $this->transactions->pull($state, (int) $employee->getKey());

        if ($error === 'access_denied') {
            // Declined: nothing is exchanged and nothing is stored. A connection the
            // employee already had is left exactly as it was.
            return ['signIn' => 'declined', 'planPermission' => $this->status($employee)['planPermission']];
        }

        if ($error !== null && $error !== '') {
            throw new ChatGptFailed('authorization_error', 'The provider returned an error ('.mb_substr($error, 0, 64).').');
        }

        if ($code === null || $code === '') {
            throw new ChatGptFailed('authorization_error', 'The callback carried no authorization code.');
        }

        $tokens = $this->tokens->exchange($code, $transaction['verifier']);

        if ($tokens['id_token'] === null) {
            throw new ChatGptFailed('id_token_invalid', 'The token response carried no ID token.');
        }

        $identity = $this->idTokens->validate($tokens['id_token'], $transaction['nonce']);

        $connection = KnxChatGptConnection::query()->updateOrCreate(
            ['organization_id' => $employee->organization_id, 'employee_id' => $employee->getKey()],
            [
                'subject' => $identity['sub'],
                'email' => $identity['email'],
                'name' => $identity['name'],
                'client_id' => $this->settings->clientId(),
                'scopes' => $tokens['scopes'],
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
                'access_expires_at' => now()->addSeconds($tokens['expires_in']),
                'plan_permission' => $this->grantsPlan($tokens['scopes']),
                'status' => KnxChatGptConnection::STATUS_CONNECTED,
            ],
        );

        return ['signIn' => 'ok', 'planPermission' => (bool) $connection->plan_permission];
    }

    /**
     * Rotate the tokens. Success replaces access, expiry, scopes and refresh token
     * together. A terminal refusal keeps the identity, drops the unusable tokens and
     * marks the connection `needs_sign_in`; a temporary failure keeps everything.
     *
     * @throws ChatGptFailed `needs_sign_in`, `refresh_failed` or `not_configured`
     */
    public function refresh(KnxChatGptConnection $connection): KnxChatGptConnection
    {
        $this->settings->assertConfigured();

        return Cache::lock('knx:chatgpt:refresh:'.$connection->getKey(), 30)->block(10, function () use ($connection): KnxChatGptConnection {
            $current = $connection->fresh();

            if ($current === null || $current->status !== KnxChatGptConnection::STATUS_CONNECTED || $current->refresh_token === null) {
                throw new ChatGptFailed('needs_sign_in', 'This connection needs a new sign-in.');
            }

            // Somebody else already rotated it while we waited for the lock.
            if ($current->refresh_token !== $connection->refresh_token && $current->access_expires_at?->isFuture()) {
                return $current;
            }

            try {
                $tokens = $this->tokens->refresh($current->refresh_token, $current->client_id);
            } catch (ChatGptFailed $failure) {
                if ($failure->reason === 'needs_sign_in') {
                    $current->forceFill([
                        'access_token' => null,
                        'refresh_token' => null,
                        'access_expires_at' => null,
                        'scopes' => [],
                        'plan_permission' => false,
                        'status' => KnxChatGptConnection::STATUS_NEEDS_SIGN_IN,
                    ])->save();
                }

                throw $failure;
            }

            $scopes = $tokens['scopes'] ?? ($current->scopes ?? []);

            $current->forceFill([
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $current->refresh_token,
                'access_expires_at' => now()->addSeconds($tokens['expires_in']),
                'scopes' => $scopes,
                'plan_permission' => $this->grantsPlan($scopes),
            ])->save();

            return $current;
        });
    }

    /**
     * The employee's access token, refreshed first when it has expired (or is about to).
     * The connection comes back too: a refresh can change the granted scopes.
     *
     * @return array{connection: KnxChatGptConnection, token: string}
     *
     * @throws ChatGptFailed `needs_sign_in` (no usable tokens) or `refresh_failed`
     */
    public function usableToken(KnxChatGptConnection $connection): array
    {
        if ($connection->status !== KnxChatGptConnection::STATUS_CONNECTED) {
            throw new ChatGptFailed('needs_sign_in', 'This connection needs a new sign-in.');
        }

        if ($connection->access_token === null || $connection->access_expires_at === null
            || $connection->access_expires_at->subSeconds(60)->isPast()) {
            $connection = $this->refresh($connection);
        }

        if ($connection->access_token === null) {
            throw new ChatGptFailed('needs_sign_in', 'This connection has no access token.');
        }

        return ['connection' => $connection, 'token' => $connection->access_token];
    }

    public function disconnect(KnxEmployee $employee): void
    {
        KnxChatGptConnection::query()
            ->where('organization_id', $employee->organization_id)
            ->where('employee_id', $employee->getKey())
            ->delete();
    }

    /** @param  list<string>  $granted */
    private function grantsPlan(array $granted): bool
    {
        return array_diff($this->settings->planScopes(), $granted) === [];
    }
}
