<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\Site;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\SiteSetting;
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
    // DatabaseTruncation, not RefreshDatabase (same rationale as
    // ConsultationEndpoint201Test): the file asserts ABSOLUTE row counts
    // (201 ⇒ one row) and RefreshDatabase-based files that ran earlier in
    // the suite commit their final rows for good — a leftover lead would
    // make every count assert see two rows.
    use DatabaseTruncation;

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

    /**
     * Registra la versión del texto de consentimiento del sitio. Es lo que **enciende**
     * el requisito: la lista config-declarada dice quién lo exige, y este ajuste dice si
     * hay algo que exigir.
     */
    private function declareConsentVersion(Site $site, string $version): void
    {
        SiteSetting::query()->create([
            'site_id' => $site->id,
            'key' => 'contact_consent_version',
            'type' => SiteSetting::TYPE_TEXT,
            'value' => $version,
        ]);
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

        // La lista config-declarada dice QUIÉN lo exige: se resuelve desde el sitio de
        // la request, nunca desde un id a fuego (decisión 1A).
        config(['website.intake_hardening.consent_version_required_site_keys' => [$site->key]]);

        // Y el sitio tiene que declarar una versión de texto que aceptar. Sin esto no
        // hay nada que exigir: ver el test de abajo, que es el caso real de hoy.
        $this->declareConsentVersion($site, '2026-10-01');

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

    /**
     * El caso REAL de hoy, y el motivo de este cambio.
     *
     * Bertels está en la lista de sitios que exigen la versión, pero
     * `contact_consent_version` está **ausente a propósito**: es un artefacto legal que el
     * cliente todavía no entregó (igual que el copy de los legales). Con el `required`
     * incondicional, TODO envío real respondía 422 — comprobado con POST reales contra el
     * trunk, porque el front de Astro no manda el campo y el openapi tampoco lo documenta.
     *
     * Un formulario no puede exigir la versión de un texto que todavía no existe, y
     * sembrar un `v1` inventado sería declarar una aceptación que no ocurrió.
     */
    public function test_a_site_without_a_declared_consent_version_does_not_require_it(): void
    {
        $site = Site::factory()->create([
            'key' => 'bertels-fixture-no-consent',
            'domain' => 'bertels-no-consent.test',
        ]);

        config(['website.intake_hardening.consent_version_required_site_keys' => [$site->key]]);

        // Sin `declareConsentVersion()`: el ajuste no está, así que no hay nada que exigir.
        $this->postJson('/v1/website/consultations?site=bertels-fixture-no-consent', $this->payload())
            ->assertCreated();

        $this->assertNull(ConsultationRequest::query()->sole()->consent_policy_version);
    }
}
