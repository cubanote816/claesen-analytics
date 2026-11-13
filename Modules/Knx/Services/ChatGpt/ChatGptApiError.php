<?php

declare(strict_types=1);

namespace Modules\Knx\Services\ChatGpt;

use Illuminate\Http\Client\Response;

/**
 * What the ChatGPT-plan API answered when it refused: HTTP status, structured error code
 * and param, request id, and the stable plan reason they map to. The documented bodies are
 * `{"detail": "..."}` (status only) or `{"error": {code, message, param}}`; usage errors can
 * also arrive inside a `response.failed` event once the stream started.
 */
final class ChatGptApiError
{
    public const USAGE_URL = 'https://chatgpt.com/settings/usage';

    private const BY_CODE = [
        'subscription_sharing_user_not_eligible' => 'plan_not_eligible',
        'subscription_sharing_usage_limit_exceeded' => 'plan_usage_limit',
        'subscription_sharing_usage_unavailable' => 'plan_unavailable',
        'subscription_sharing_unsupported_capability' => 'plan_unsupported_capability',
        'subscription_sharing_route_not_supported' => 'plan_policy',
        'subscription_sharing_invalid_user' => 'plan_not_accepted',
        'subscription_sharing_user_unavailable' => 'plan_unavailable',
        'chatpass_v2_scope_not_authorized' => 'plan_not_accepted',
        'chatpass_v2_invalid_authorization_context' => 'plan_not_accepted',
    ];

    public function __construct(
        public readonly ?int $status,
        public readonly ?string $code,
        public readonly ?string $param,
        public readonly ?string $requestId,
        public readonly string $message,
    ) {}

    public static function fromResponse(Response $response): self
    {
        $body = $response->json();
        $body = is_array($body) ? $body : [];
        $error = $body['error'] ?? null;

        $code = is_array($error) ? ($error['code'] ?? $error['type'] ?? null) : (is_string($error) ? $error : ($body['code'] ?? null));
        $param = is_array($error) ? ($error['param'] ?? null) : null;
        $message = is_array($error) ? ($error['message'] ?? null) : ($body['detail'] ?? null);

        return new self(
            $response->status(),
            is_string($code) ? $code : null,
            is_string($param) ? $param : null,
            $response->header('x-request-id') ?: null,
            is_string($message) ? mb_substr($message, 0, 300) : '',
        );
    }

    /** An error carried by a `response.failed` / `error` stream event. */
    public static function fromEvent(mixed $error, ?string $requestId): self
    {
        $error = is_array($error) ? $error : [];
        $code = $error['code'] ?? null;
        $param = $error['param'] ?? null;
        $message = $error['message'] ?? null;

        return new self(
            null,
            is_string($code) ? $code : null,
            is_string($param) ? $param : null,
            $requestId,
            is_string($message) ? mb_substr($message, 0, 300) : '',
        );
    }

    public function reason(): string
    {
        if ($this->code !== null && isset(self::BY_CODE[$this->code])) {
            return self::BY_CODE[$this->code];
        }

        return match (true) {
            $this->code !== null => 'provider_error',
            $this->status === 401 => 'plan_not_accepted',
            $this->status === 403 => 'plan_policy',
            $this->status === 503 => 'plan_unavailable',
            default => 'provider_error',
        };
    }

    public function describe(): string
    {
        $what = $this->code ?? ($this->status !== null ? "HTTP {$this->status}" : 'an error');
        $text = "ChatGPT answered {$what}".($this->message !== '' ? ": {$this->message}" : '').'.';

        return $this->reason() === 'plan_usage_limit' ? $text.' Check '.self::USAGE_URL : $text;
    }
}
