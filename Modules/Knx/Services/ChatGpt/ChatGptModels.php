<?php

declare(strict_types=1);

namespace Modules\Knx\Services\ChatGpt;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Knx\Models\KnxChatGptConnection;

/**
 * Which models the employee's plan can use (`GET {resource}/models`), as the provider
 * lists them. The docs define no image field: a model that carries an explicit input
 * modality list says `yes`/`no`, anything else is `unknown` until a real request shows it.
 * Image GENERATION is not part of this flow and is never called: always `unsupported`.
 */
final class ChatGptModels
{
    public function __construct(
        private readonly ChatGptSettings $settings,
        private readonly ChatGptConnections $connections,
    ) {}

    /**
     * @return list<array{slug: string, displayName: string, imageInput: string}>
     *
     * @throws ChatGptFailed `models_failed` (carries the provider's answer), or a refresh failure
     */
    public function list(KnxChatGptConnection $connection): array
    {
        $token = $this->connections->usableToken($connection)['token'];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->get(rtrim($this->settings->resource(), '/').'/models');
        } catch (ConnectionException) {
            throw new ChatGptFailed('models_failed', 'The models endpoint could not be reached.');
        }

        if (! $response->successful()) {
            $error = ChatGptApiError::fromResponse($response);

            throw new ChatGptFailed('models_failed', $error->describe(), api: $error);
        }

        $models = [];
        foreach ((array) $response->json('models', []) as $entry) {
            if (! is_array($entry) || ($entry['visibility'] ?? null) !== 'list' || ! is_string($entry['slug'] ?? null)) {
                continue;
            }
            $models[] = [
                'slug' => $entry['slug'],
                'displayName' => is_string($entry['display_name'] ?? null) ? $entry['display_name'] : $entry['slug'],
                'imageInput' => $this->imageInput($entry),
            ];
        }

        return $models;
    }

    /** @param  array<string, mixed>  $entry */
    private function imageInput(array $entry): string
    {
        $modalities = $entry['input_modalities'] ?? $entry['modalities']['input'] ?? null;

        if (! is_array($modalities)) {
            return 'unknown';
        }

        return in_array('image', $modalities, true) ? 'yes' : 'no';
    }
}
