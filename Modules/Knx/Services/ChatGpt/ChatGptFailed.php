<?php

declare(strict_types=1);

namespace Modules\Knx\Services\ChatGpt;

use RuntimeException;

/**
 * The ChatGPT connection could not do what was asked. `reason` is a stable code for the
 * API (`chatgpt_<reason>`): `not_configured`, `state_invalid`, `authorization_error`,
 * `discovery_failed`, `token_exchange_failed`, `id_token_invalid`, `refresh_failed`,
 * `needs_sign_in`. The message never carries a token.
 */
final class ChatGptFailed extends RuntimeException
{
    /** @param  list<string>  $missingSettings */
    public function __construct(
        public readonly string $reason,
        string $message = '',
        public readonly array $missingSettings = [],
        public readonly ?ChatGptApiError $api = null,
    ) {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
