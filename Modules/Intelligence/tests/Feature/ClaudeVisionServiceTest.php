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

    /**
     * CLA-389 — an out-of-catalog brand/model guess can never be "identified":
     * it is unverifiable against real hardware data, so it's clamped to "probable"
     * regardless of what the model itself claimed.
     */
    public function test_it_downgrades_an_out_of_catalog_suggestion_claiming_identified_to_probable(): void
    {
        $service = new ClaudeVisionService;

        $clamped = $service->clampExternalSuggestions([
            'catalog_id' => null,
            'suggested_brand' => 'Schréder',
            'suggested_model' => 'OMNISTAR LED XL',
            'confidence' => 0.9,
            'evidence' => ['distinctive trapezoidal housing'],
            'status' => 'identified',
        ]);

        $this->assertSame('probable', $clamped['status']);
    }

    public function test_it_leaves_a_real_catalog_match_claiming_identified_untouched(): void
    {
        $service = new ClaudeVisionService;

        $clamped = $service->clampExternalSuggestions([
            'catalog_id' => 27,
            'suggested_brand' => null,
            'suggested_model' => null,
            'confidence' => 0.95,
            'evidence' => ['exact label match'],
            'status' => 'identified',
        ]);

        $this->assertSame('identified', $clamped['status']);
    }

    public function test_it_leaves_a_genuine_unknown_candidate_untouched(): void
    {
        $service = new ClaudeVisionService;

        $clamped = $service->clampExternalSuggestions([
            'catalog_id' => null,
            'suggested_brand' => null,
            'suggested_model' => null,
            'confidence' => 0,
            'evidence' => [],
            'status' => 'unknown',
        ]);

        $this->assertSame('unknown', $clamped['status']);
    }

    /**
     * CLA-390 — same abstention contract as identifyLuminaires(), for the
     * separate frame-type-matching method.
     */
    public function test_identify_frame_type_abstains_without_hitting_the_network_when_the_api_key_is_missing(): void
    {
        Config::set('services.anthropic.key', null);

        $service = new ClaudeVisionService;

        $result = $service->identifyFrameType(
            base64_encode('fake-image-bytes'),
            'image/jpeg',
            [['id' => 1, 'name' => 'Fixed cross-arm headframe']]
        );

        $this->assertSame('unknown', $result['status']);
        $this->assertSame([], $result['candidates']);
    }

    public function test_identify_frame_type_abstains_when_the_catalog_is_empty(): void
    {
        Config::set('services.anthropic.key', 'test-key');

        $service = new ClaudeVisionService;

        $result = $service->identifyFrameType(
            base64_encode('fake-image-bytes'),
            'image/jpeg',
            []
        );

        $this->assertSame('unknown', $result['status']);
        $this->assertSame([], $result['candidates']);
    }

    /**
     * CLA-391 (CLA-390 Fase 2) — same abstention contract, for the
     * multi-luminaire detection method (returns "detections", not
     * "candidates").
     */
    public function test_detect_luminaires_in_frame_abstains_without_hitting_the_network_when_the_api_key_is_missing(): void
    {
        Config::set('services.anthropic.key', null);

        $service = new ClaudeVisionService;

        $result = $service->detectLuminairesInFrame(
            base64_encode('fake-image-bytes'),
            'image/jpeg',
            [['id' => 1, 'name' => 'Test', 'product_family' => null, 'model_reference' => null, 'typical_application' => null, 'brand' => null, 'group_name' => null]]
        );

        $this->assertSame('unknown', $result['status']);
        $this->assertSame([], $result['detections']);
    }

    public function test_detect_luminaires_in_frame_abstains_when_the_catalog_is_empty(): void
    {
        Config::set('services.anthropic.key', 'test-key');

        $service = new ClaudeVisionService;

        $result = $service->detectLuminairesInFrame(
            base64_encode('fake-image-bytes'),
            'image/jpeg',
            []
        );

        $this->assertSame('unknown', $result['status']);
        $this->assertSame([], $result['detections']);
    }
}
