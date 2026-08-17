<?php

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Support\Facades\Config;
use Modules\Intelligence\Services\ClaudeVisionService;
use Tests\TestCase;

/**
 * CLA-386 — abstention rules are the whole point of this service, so they're
 * covered directly without touching the network: a missing API key or an
 * empty catalog must never reach Anthropic, and both must resolve to
 * "unknown" rather than throwing or guessing.
 */
class ClaudeVisionServiceTest extends TestCase
{
    public function test_it_abstains_without_hitting_the_network_when_the_api_key_is_missing(): void
    {
        Config::set('services.anthropic.key', null);

        $service = new ClaudeVisionService;

        $result = $service->identifyLuminaires(
            base64_encode('fake-image-bytes'),
            'image/jpeg',
            [['id' => 1, 'name' => 'Test', 'product_family' => null, 'model_reference' => null, 'typical_application' => null, 'brand' => null, 'group_name' => null]]
        );

        $this->assertSame('unknown', $result['status']);
        $this->assertSame([], $result['candidates']);
    }

    public function test_it_abstains_when_the_catalog_is_empty(): void
    {
        Config::set('services.anthropic.key', 'test-key');

        $service = new ClaudeVisionService;

        $result = $service->identifyLuminaires(
            base64_encode('fake-image-bytes'),
            'image/jpeg',
            []
        );

        $this->assertSame('unknown', $result['status']);
        $this->assertSame([], $result['candidates']);
    }
}
