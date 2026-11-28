<?php

declare(strict_types=1);

namespace Modules\Intelligence\TextGeneration;

use Anthropic\Client;

/**
 * CLA-714: thin seam over the Anthropic SDK so the driver can be tested
 * without network access. Tests bind a subclass in the container.
 */
class AnthropicMessageClient
{
    /** @param  array<string, mixed>  $params  named arguments for Messages::create() */
    public function createMessage(string $apiKey, array $params): object
    {
        return (new Client(apiKey: $apiKey))->messages->create(...$params);
    }
}
