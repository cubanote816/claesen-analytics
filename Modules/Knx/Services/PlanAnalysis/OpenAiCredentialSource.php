<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

/**
 * Where the OpenAI adapter gets the bearer token for the Responses API.
 *
 * Kept apart from the adapter on purpose: today the only source is an API key
 * (OpenAiApiKeyCredentials). A "Sign in with ChatGPT" plan token, once that access is
 * approved, would be ANOTHER implementation; the adapter does not change.
 */
interface OpenAiCredentialSource
{
    /** A stable name for logs and the API (`api_key`, ...). */
    public function id(): string;

    /** The bearer token, or null when this source has none. */
    public function bearerToken(): ?string;
}
