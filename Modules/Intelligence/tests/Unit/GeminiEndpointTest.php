<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Unit;

use Modules\Intelligence\Support\GeminiEndpoint;
use Tests\TestCase;

/** CLA-729 S1: single Vertex endpoint builder driven by services.gemini.*. */
final class GeminiEndpointTest extends TestCase
{
    private const PROJECT = 'gen-lang-client-0849598291';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.gemini.url' => null,
            'services.gemini.project' => self::PROJECT,
            'services.gemini.location' => 'global',
            'services.gemini.model' => 'gemini-3.5-flash-lite',
            'services.gemini.timeout' => 30,
        ]);
    }

    public function test_config_defaults_match_the_migration_target(): void
    {
        $defaults = require base_path('config/services.php');

        $this->assertSame(self::PROJECT, $defaults['gemini']['project']);
        $this->assertSame('global', $defaults['gemini']['location']);
        $this->assertSame('gemini-3.5-flash-lite', $defaults['gemini']['model']);
        $this->assertSame(30, $defaults['gemini']['timeout']);
    }

    public function test_default_is_the_global_endpoint(): void
    {
        $this->assertSame(
            'https://aiplatform.googleapis.com/v1/projects/'.self::PROJECT.'/locations/global/publishers/google/models/gemini-3.5-flash-lite:generateContent',
            GeminiEndpoint::generateContentUrl(),
        );
    }

    public function test_regional_location_uses_the_regional_host(): void
    {
        config(['services.gemini.location' => 'eu']);

        $this->assertSame(
            'https://eu-aiplatform.googleapis.com/v1/projects/'.self::PROJECT.'/locations/eu/publishers/google/models/gemini-3.5-flash-lite:generateContent',
            GeminiEndpoint::generateContentUrl(),
        );
    }

    public function test_model_and_project_overrides_are_used(): void
    {
        config(['services.gemini.model' => 'gemini-x', 'services.gemini.project' => 'other-proj']);

        $this->assertSame(
            'https://aiplatform.googleapis.com/v1/projects/other-proj/locations/global/publishers/google/models/gemini-x:generateContent',
            GeminiEndpoint::generateContentUrl(),
        );
    }

    public function test_full_url_override_wins_unchanged(): void
    {
        config(['services.gemini.url' => 'https://example.test/custom:generateContent', 'services.gemini.location' => 'eu']);

        $this->assertSame('https://example.test/custom:generateContent', GeminiEndpoint::generateContentUrl());
    }

    public function test_timeout_default_and_override(): void
    {
        $this->assertSame(30, GeminiEndpoint::timeout());

        config(['services.gemini.timeout' => '45']);
        $this->assertSame(45, GeminiEndpoint::timeout());
    }

    public function test_model_comes_from_config_or_from_the_override_url(): void
    {
        $this->assertSame('gemini-3.5-flash-lite', GeminiEndpoint::model());

        config(['services.gemini.url' => 'https://us-central1-aiplatform.googleapis.com/v1/projects/p/locations/us-central1/publishers/google/models/gemini-2.5-flash:generateContent']);
        $this->assertSame('gemini-2.5-flash', GeminiEndpoint::model());

        config(['services.gemini.url' => 'https://example.test/custom']);
        $this->assertSame('', GeminiEndpoint::model());
    }

    public function test_thinking_config_only_for_gemini_3_or_later(): void
    {
        $minimal = ['thinkingLevel' => 'MINIMAL'];

        $this->assertSame($minimal, GeminiEndpoint::thinkingConfig());

        config(['services.gemini.model' => 'gemini-4-x']);
        $this->assertSame($minimal, GeminiEndpoint::thinkingConfig());

        config(['services.gemini.model' => 'gemini-2.5-flash']);
        $this->assertSame([], GeminiEndpoint::thinkingConfig());

        config(['services.gemini.model' => '']);
        $this->assertSame([], GeminiEndpoint::thinkingConfig());

        config(['services.gemini.url' => 'https://example.test/custom']);
        config(['services.gemini.model' => 'gemini-3.5-flash-lite']);
        $this->assertSame([], GeminiEndpoint::thinkingConfig());
    }
}
