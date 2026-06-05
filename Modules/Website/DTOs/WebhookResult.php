<?php

namespace Modules\Website\DTOs;

readonly class WebhookResult
{
    public function __construct(
        public bool    $success,
        public int     $statusCode,
        public ?string $errorMessage = null,
    ) {}

    public static function ok(int $statusCode = 202): self
    {
        return new self(success: true, statusCode: $statusCode);
    }

    public static function failure(int $statusCode, string $errorMessage): self
    {
        return new self(success: false, statusCode: $statusCode, errorMessage: $errorMessage);
    }
}
