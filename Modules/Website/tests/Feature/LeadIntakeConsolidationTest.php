<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Prospects\Models\Prospect;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Services\ConsultationService;
use Tests\TestCase;

/**
 * F4/CLA-478 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Consolidar endpoints públicos de contacto en un intake site-aware" —
 * covers the gaps found auditing the two pre-existing controllers before
 * this ticket: only /contact-email ever created a Prospects lead (not
 * /consultations); ConsultationController's manual catch block returned
 * $e->getMessage() straight into the public JSON response; source was
 * either a client-submitted free-text field or an unfiltered Referer
 * header; site_id was hardcoded to Claesen regardless of the resolved
 * site.
 *
 * Every assertion below is scoped by this file's own unique email per test
 * (never a bare ConsultationRequest::count()) — Modules/Website/tests/
 * Feature/{ConsultationEmailTest,ConsultationEndpoint201Test,
 * MigrationJsonConversionTest}.php use DatabaseTruncation, which does not
 * re-truncate after its own class finishes; whichever of them runs earlier
 * in the same PHPUnit process can leave real, committed consultation rows
 * behind that a bare count() here would wrongly pick up (this file uses
 * RefreshDatabase, whose transaction sees pre-existing committed data from
 * before it started) — found by this ticket's own first full-suite run,
 * not assumed.
 */
final class LeadIntakeConsolidationTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake();
        config(['website.consultation_notification_email' => null]);
    }

    public function test_both_intake_endpoints_create_exactly_one_prospect_lead(): void
    {
        $email = 'jan-'.uniqid().'@example.test';

        $this->postJson('/v1/website/consultations', [
            'name' => 'Jan Claesen',
            'email' => $email,
            'message' => 'Interested in stadium lighting.',
        ])->assertCreated();

        $this->assertSame(1, $this->prospectCountFor($email));
        $this->assertSame(1, ConsultationRequest::query()->where('email', $email)->count());

        // A second submission via the OTHER endpoint, same email — must
        // reuse the same Prospect (LeadService::persistContactLead()'s own
        // dedup-by-email), not create a second one, while still creating
        // its own second ConsultationRequest (each real inquiry is its own
        // lead record even if the person is already a known prospect).
        $this->postJson('/v1/website/contact-email', [
            'name' => 'Jan Claesen',
            'email' => $email,
            'message' => 'Following up on my earlier request.',
        ])->assertCreated();

        $this->assertSame(1, $this->prospectCountFor($email));
        $this->assertSame(2, ConsultationRequest::query()->where('email', $email)->count());
    }

    public function test_consultation_controller_never_leaks_an_internal_exception_message(): void
    {
        // Force a real internal failure so the manual catch block in
        // ConsultationController::store() actually engages, and assert the
        // response never contains the raw exception text — the historical
        // version of this handler returned $e->getMessage() directly.
        $this->mock(ConsultationService::class, function ($mock): void {
            $mock->shouldReceive('handlePublicIntake')
                ->andThrow(new \RuntimeException('SQLSTATE[23000]: /var/www/html/some/internal/path.php leaked detail'));
        });

        $response = $this->postJson('/v1/website/consultations', [
            'name' => 'Jan Claesen',
            'email' => 'jan-leak-test@example.test',
            'message' => 'Test.',
        ])->assertStatus(500);

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('/var/www/html', $response->getContent());
        $response->assertExactJson(['message' => 'Failed to create consultation request.']);
    }

    public function test_client_submitted_source_is_never_trusted_verbatim(): void
    {
        $email = 'jan-'.uniqid().'@example.test';

        $this->postJson('/v1/website/consultations', [
            'name' => 'Jan Claesen',
            'email' => $email,
            'message' => 'Test.',
            'source' => '<script>alert(1)</script>totally-fake-source',
        ])->assertCreated();

        $consultation = ConsultationRequest::query()->where('email', $email)->firstOrFail();
        $this->assertSame('website', $consultation->source);
    }

    public function test_source_is_derived_from_an_explicit_utm_source_query_param(): void
    {
        $email = 'jan-'.uniqid().'@example.test';

        $this->postJson('/v1/website/consultations?utm_source=google-ads&utm_medium=cpc&utm_campaign=summer26', [
            'name' => 'Jan Claesen',
            'email' => $email,
            'message' => 'Test.',
        ])->assertCreated();

        $consultation = ConsultationRequest::query()->where('email', $email)->firstOrFail();
        $this->assertSame('google-ads', $consultation->source);
        // assertEquals, not assertSame: array key order isn't guaranteed to
        // survive the JSON round-trip (same convention as CLA-468/CLA-469).
        $this->assertEquals([
            'source' => 'google-ads',
            'medium' => 'cpc',
            'campaign' => 'summer26',
        ], $consultation->custom_fields['utm']);
    }

    public function test_source_falls_back_to_the_referer_hostname_when_no_utm_param_is_present(): void
    {
        $email = 'jan-'.uniqid().'@example.test';

        $this->postJson('/v1/website/consultations', [
            'name' => 'Jan Claesen',
            'email' => $email,
            'message' => 'Test.',
        ], ['Referer' => 'https://claesen-verlichting.be/contact?foo=bar'])->assertCreated();

        $consultation = ConsultationRequest::query()->where('email', $email)->firstOrFail();
        $this->assertSame('claesen-verlichting.be', $consultation->source);
    }

    public function test_a_repeated_request_with_the_same_idempotency_key_does_not_create_a_second_lead(): void
    {
        $email = 'jan-'.uniqid().'@example.test';
        $payload = [
            'name' => 'Jan Claesen',
            'email' => $email,
            'message' => 'Test.',
        ];
        $headers = ['Idempotency-Key' => 'retry-'.uniqid()];

        $first = $this->postJson('/v1/website/consultations', $payload, $headers)->assertCreated();
        $second = $this->postJson('/v1/website/consultations', $payload, $headers)->assertCreated();

        $this->assertSame(1, ConsultationRequest::query()->where('email', $email)->count());
        $this->assertSame($first->json('data.id'), $second->json('data.id'));
    }

    public function test_without_an_idempotency_key_two_deliberate_posts_still_create_two_leads(): void
    {
        // CLA-532's own documented guarantee, preserved: the endpoint is
        // not idempotent by default.
        $email = 'jan-'.uniqid().'@example.test';
        $payload = [
            'name' => 'Jan Claesen',
            'email' => $email,
            'message' => 'Test.',
        ];

        $this->postJson('/v1/website/consultations', $payload)->assertCreated();
        $this->postJson('/v1/website/consultations', $payload)->assertCreated();

        $this->assertSame(2, ConsultationRequest::query()->where('email', $email)->count());
    }

    public function test_a_bertels_fixture_site_consultation_is_attributed_to_that_site_not_claesen(): void
    {
        $this->enableOrganizationEnforcement();
        $bertelsSite = $this->bertelsFixtureSite();
        $email = 'jan-'.uniqid().'@bertels.test';

        $this->postJson('/v1/website/consultations?site='.$bertelsSite->key, [
            'name' => 'Jan Bertels',
            'email' => $email,
            'message' => 'Test.',
        ])->assertCreated();

        $consultation = ConsultationRequest::withoutGlobalScope('site')->where('email', $email)->firstOrFail();
        $this->assertSame($bertelsSite->id, $consultation->site_id);

        // ADR D11: Prospects/audiences stay 100% Claesen — a Bertels lead
        // never feeds that domain.
        $this->assertSame(0, $this->prospectCountFor($email));
    }

    private function prospectCountFor(string $email): int
    {
        return Prospect::query()
            ->whereHas('locations', fn ($q) => $q->where('email', strtolower($email)))
            ->count();
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }
}
