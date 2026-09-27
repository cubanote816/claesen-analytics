<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Site;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Models\LegalDocument;
use Tests\TestCase;

/**
 * CLA-479 (dynamic part): the public per-site legal document endpoint.
 *
 * Freezes the wire contract of GET /v1/website/legal/{docId} (shape, 404s,
 * per-site isolation) and the site-scoped locale policy of CLA-611 gap G9:
 * a strict site never serves another locale's text; tolerant sites (Claesen)
 * keep the historical fallback untouched.
 */
final class LegalDocumentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // HasAiTranslations fires on save (QUEUE_CONNECTION=sync); keep tests
        // offline and deterministic — same approach as the baseline contract test.
        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => []]);
        });

        app()->setLocale('nl');
    }

    public function test_it_serves_a_seeded_document_with_the_full_contract_shape(): void
    {
        LegalDocument::create([
            'site_id' => Site::claesenId(),
            'doc_id' => 'privacy',
            'title' => ['nl' => 'Privacyverklaring'],
            'body' => ['nl' => "Paragraaf één.\n\nParagraaf twee."],
            'version' => '2026-10-01',
            'effective_date' => '2026-10-01',
        ]);

        $this->getJson('/v1/website/legal/privacy', ['Accept-Language' => 'nl'])
            ->assertOk()
            ->assertJsonPath('data.doc_id', 'privacy')
            ->assertJsonPath('data.version', '2026-10-01')
            ->assertJsonPath('data.effective_date', '2026-10-01')
            ->assertJsonPath('data.locale', 'nl')
            ->assertJsonPath('data.title', 'Privacyverklaring')
            ->assertJsonPath('data.body', "Paragraaf één.\n\nParagraaf twee.")
            ->assertJsonPath('data.translation_status', 'machine');
    }

    public function test_it_reports_missing_when_the_requested_locale_has_no_translation(): void
    {
        LegalDocument::create([
            'site_id' => Site::claesenId(),
            'doc_id' => 'cookies',
            'title' => ['nl' => 'Cookiebeleid'],
            'body' => ['nl' => 'Body.'],
            'version' => '2026-10-01',
            'effective_date' => '2026-10-01',
        ]);

        // Strict site: an untranslated locale resolves to null AND is
        // reported as missing (CLA-611 G9 — never another locale's text).
        config(['website.public_api.strict_locale_site_keys' => [Site::find(Site::claesenId())->key]]);

        $this->getJson('/v1/website/legal/cookies', ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.locale', 'fr')
            ->assertJsonPath('data.title', null)
            ->assertJsonPath('data.body', null)
            ->assertJsonPath('data.translation_status', 'missing');
    }

    public function test_a_strict_site_never_serves_dutch_for_a_french_request(): void
    {
        $site = Site::factory()->create([
            'key' => 'strict-fixture',
            'domain' => 'strict-fixture.test',
        ]);

        LegalDocument::create([
            'site_id' => $site->id,
            'doc_id' => 'terms',
            'title' => ['nl' => 'Algemene voorwaarden'],
            'body' => ['nl' => 'Nederlandse tekst.'],
            'version' => '2026-10-01',
            'effective_date' => '2026-10-01',
        ]);

        config(['website.public_api.strict_locale_site_keys' => ['strict-fixture']]);

        $this->getJson('/v1/website/legal/terms?site=strict-fixture', ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.title', null)
            ->assertJsonPath('data.body', null);
    }

    public function test_a_tolerant_site_keeps_the_historical_nl_fallback(): void
    {
        // Claesen-like site NOT on the strict list: the frozen fallback chain
        // (PortfolioApiTest) applies to the new surface too — a missing FR
        // translation falls back to nl, exactly as the legacy behaviour does.
        $site = Site::factory()->create([
            'key' => 'tolerant-fixture',
            'domain' => 'tolerant-fixture.test',
        ]);

        LegalDocument::create([
            'site_id' => $site->id,
            'doc_id' => 'terms',
            'title' => ['nl' => 'Algemene voorwaarden'],
            'body' => ['nl' => 'Nederlandse tekst.'],
            'version' => '2026-10-01',
            'effective_date' => '2026-10-01',
        ]);

        $this->getJson('/v1/website/legal/terms?site=tolerant-fixture', ['Accept-Language' => 'fr'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Algemene voorwaarden')
            ->assertJsonPath('data.body', 'Nederlandse tekst.');
    }

    public function test_an_unknown_doc_id_is_a_404(): void
    {
        $this->getJson('/v1/website/legal/nonexistent')->assertNotFound();
    }

    public function test_a_document_of_another_site_is_not_reachable(): void
    {
        $bertelsSite = Site::factory()->create(['key' => 'bertels-fixture-docs']);

        LegalDocument::create([
            'site_id' => Site::claesenId(),
            'doc_id' => 'privacy',
            'title' => ['nl' => 'Privacyverklaring Claesen'],
            'body' => ['nl' => 'Body.'],
            'version' => '2026-10-01',
            'effective_date' => '2026-10-01',
        ]);

        $bertelsDoc = LegalDocument::create([
            'site_id' => $bertelsSite->id,
            'doc_id' => 'privacy',
            'title' => ['nl' => 'Privacyverklaring Bertels'],
            'body' => ['nl' => 'Body Bertels.'],
            'version' => '2026-10-01',
            'effective_date' => '2026-10-01',
        ]);

        // Same doc_id, different site: the resolved site must only ever see
        // its own row (site-scoped row isolation, ADR D3).
        $this->getJson('/v1/website/legal/privacy?site=bertels-fixture-docs')
            ->assertOk()
            ->assertJsonPath('data.title', 'Privacyverklaring Bertels');
        $this->getJson('/v1/website/legal/privacy', ['Accept-Language' => 'nl'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Privacyverklaring Claesen');

        // No document at all for the bertels site under a different doc_id.
        $this->getJson('/v1/website/legal/terms?site=bertels-fixture-docs')
            ->assertNotFound();

        $this->assertNotNull($bertelsDoc->fresh());
    }
}
