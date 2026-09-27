<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\Site;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Models\ConsultationRequest;
use Tests\TestCase;

/**
 * CLA-484: the extended intake validation (segment, postal_code, subject,
 * locale, consent_version) and the site-conditional consent_version rule
 * (approver decision 1A): Claesen without the field stays 201/byte-identical,
 * a consent-requiring site without it gets 422.
 *
 * The pre-existing guarantees are frozen elsewhere and must keep passing:
 * 201 ⇒ one row + mailer failure ⇒ no 500 (ConsultationEndpoint201Test,
 * CLA-532) and 429 after the throttle (WebsitePublicApiContractTest).
 */
final class IntakeBertelsFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => []]);
        });

        Http::fake();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Bertels Visitor',
            'email' => 'visitor@example.test',
            'message' => 'Ik heb interesse in een ombouw van mijn verdelingbord.',
        ], $overrides);
    }

    public function test_the_new_fields_are_validated_and_persisted(): void
    {
        $this->postJson('/v1/website/consultations', $this->payload([
            'segment' => 'particulier',
            'postal_code' => '2490',
            'subject' => 'Herstelling verdeelbord',
            'locale' => 'nl',
            'consent_version' => 'v1',
        ]))->assertCreated();

        $lead = ConsultationRequest::query()->sole();

        $this->assertSame('particulier', $lead->segment);
        $this->assertSame('2490', $lead->postal_code);
        $this->assertSame('Herstelling verdeelbord', $lead->subject);
        $this->assertSame('nl', $lead->locale);
        $this->assertSame('v1', $lead->consent_policy_version);
    }

    public function test_invalid_values_are_rejected_with_422(): void
    {
        $this->postJson('/v1/website/consultations', $this->payload([
            'segment' => 'gouvernement',   // not in particulier|bedrijf|industrie
            'postal_code' => str_repeat('9', 17), // max 16
            'locale' => 'es',              // canonical locales are nl,fr,en,de
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['segment', 'postal_code', 'locale']);
    }

    public function test_a_claesen_request_without_consent_version_stays_201(): void
    {
        // Decision 1A, Claesen direction: the live form doesn't send
        // consent_version and must keep working byte-identically.
        $this->postJson('/v1/website/consultations', $this->payload())
            ->assertCreated();

        $this->assertSame(1, ConsultationRequest::query()->count());
        $this->assertNull(ConsultationRequest::query()->sole()->consent_policy_version);
    }

    public function test_a_consent_requiring_site_without_consent_version_gets_422(): void
    {
        $site = Site::factory()->create([
            'key' => 'bertels-fixture-consent',
            'domain' => 'bertels-consent.test',
        ]);

        // Decision 1A, Bertels direction: resolved from the request's site —
        // the config list names the site KEY, never a hardcoded id.
        config(['website.intake_hardening.consent_version_required_site_keys' => [$site->key]]);

        $this->postJson('/v1/website/consultations?site=bertels-fixture-consent', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['consent_version']);

        $this->postJson('/v1/website/consultations?site=bertels-fixture-consent', $this->payload([
            'consent_version' => '2026-10-01',
        ]))->assertCreated();

        $lead = ConsultationRequest::query()->sole();
        $this->assertSame($site->id, $lead->site_id);
        $this->assertSame('2026-10-01', $lead->consent_policy_version);
    }
}
