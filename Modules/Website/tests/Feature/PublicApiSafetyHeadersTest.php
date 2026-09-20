<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Website\Models\Project;
use Tests\TestCase;

/**
 * F3/CLA-471 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Errores públicos sanitizados" and "Caché, ETag o estrategia equivalente"
 * — the two remaining CLA-471 criteria closed in this pass.
 *
 * The sanitized-error gap was found verifying this ticket, not assumed:
 * with APP_DEBUG=true (the default in .env/.env.example) a plain 404 on
 * this public, unauthenticated route group leaked a full stack trace
 * including vendor file paths (confirmed via a raw kernel dispatch before
 * bootstrap/app.php's exceptions renderer existed). ValidationException's
 * own rendering was already safe and is asserted untouched here.
 */
final class PublicApiSafetyHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_404_on_the_public_api_never_leaks_a_stack_trace_regardless_of_app_debug(): void
    {
        config(['app.debug' => true]);

        $response = $this->getJson('/v1/website/projects/does-not-exist')->assertNotFound();

        $response->assertExactJson(['message' => 'Not found.']);
        $this->assertStringNotContainsString('vendor/laravel', $response->getContent());
        $this->assertStringNotContainsString('"trace"', $response->getContent());
        $this->assertStringNotContainsString('"file"', $response->getContent());
    }

    public function test_validation_errors_on_the_public_api_are_left_untouched(): void
    {
        // Confirms the sanitizer's ValidationException bypass — the
        // consultations endpoint's own field-level error format must
        // survive, not get flattened into the generic envelope.
        $response = $this->postJson('/v1/website/consultations', [])->assertStatus(422);

        $this->assertArrayHasKey('errors', $response->json());
    }

    public function test_a_non_website_route_is_unaffected_by_the_sanitizer(): void
    {
        config(['app.debug' => true]);

        $response = $this->getJson('/this-route-does-not-exist-anywhere');

        $response->assertNotFound();
        $this->assertNotSame(['message' => 'Not found.'], $response->json());
    }

    public function test_get_responses_carry_an_etag_and_a_matching_conditional_request_gets_a_304(): void
    {
        Project::factory()->create(['published' => true]);

        $first = $this->getJson('/v1/website/projects')->assertOk();
        $etag = $first->headers->get('ETag');

        $this->assertNotEmpty($etag);
        $this->assertStringContainsString('max-age=', (string) $first->headers->get('Cache-Control'));

        $this->withHeaders(['If-None-Match' => $etag])
            ->getJson('/v1/website/projects')
            ->assertStatus(304);
    }

    public function test_a_post_response_never_gets_cache_headers(): void
    {
        // Status doesn't matter here (this payload may or may not pass
        // StoreConsultationRequest's own validation) — the only thing
        // under test is that SetPublicApiCacheHeaders never touches a
        // non-GET request, regardless of the outcome.
        $response = $this->postJson('/v1/website/consultations', []);

        $this->assertNull($response->headers->get('ETag'));
    }
}
