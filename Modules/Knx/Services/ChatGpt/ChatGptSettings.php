<?php

declare(strict_types=1);

namespace Modules\Knx\Services\ChatGpt;

/**
 * The registered-client settings (config `knx.chatgpt`). "Configured" means the client
 * is registered: client id + redirect URI, plus the secret for a confidential client.
 */
final class ChatGptSettings
{
    public const AUTH_NONE = 'none';

    public const AUTH_BASIC = 'client_secret_basic';

    /** @return list<string> env names of the settings that are still missing */
    public function missing(): array
    {
        $missing = [];

        if ($this->clientId() === '') {
            $missing[] = 'KNX_CHATGPT_CLIENT_ID';
        }

        if ($this->redirectUri() === '') {
            $missing[] = 'KNX_CHATGPT_REDIRECT_URI';
        }

        if (! in_array($this->tokenAuth(), [self::AUTH_NONE, self::AUTH_BASIC], true)) {
            $missing[] = 'KNX_CHATGPT_TOKEN_AUTH';
        } elseif ($this->tokenAuth() === self::AUTH_BASIC && $this->clientSecret() === '') {
            $missing[] = 'KNX_CHATGPT_CLIENT_SECRET';
        }

        return $missing;
    }

    public function configured(): bool
    {
        return $this->missing() === [];
    }

    public function assertConfigured(): void
    {
        $missing = $this->missing();

        if ($missing !== []) {
            throw new ChatGptFailed('not_configured', 'ChatGPT sign-in is not configured.', $missing);
        }
    }

    public function clientId(): string
    {
        return $this->string('client_id');
    }

    public function clientSecret(): string
    {
        return $this->string('client_secret');
    }

    public function tokenAuth(): string
    {
        return $this->string('token_auth') ?: self::AUTH_NONE;
    }

    public function redirectUri(): string
    {
        return $this->string('redirect_uri');
    }

    public function discoveryUrl(): string
    {
        return $this->string('discovery_url');
    }

    public function resource(): string
    {
        return $this->string('resource');
    }

    /** @return list<string> */
    public function identityScopes(): array
    {
        return array_values((array) config('knx.chatgpt.scopes.identity', []));
    }

    /** @return list<string> */
    public function planScopes(): array
    {
        return array_values((array) config('knx.chatgpt.scopes.plan', []));
    }

    /** @return list<string> */
    public function requestedScopes(): array
    {
        return [...$this->identityScopes(), ...$this->planScopes()];
    }

    private function string(string $key): string
    {
        $value = config("knx.chatgpt.{$key}");

        return is_string($value) ? trim($value) : '';
    }
}
