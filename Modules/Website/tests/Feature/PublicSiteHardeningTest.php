<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\Site;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Models\ConsultationRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * CLA-706: hardening of the public website API around the `site` selector.
 *
 *  - A non-string `site` (`site[]=x`) is a client error (400) on every
 *    /v1/website route, never a 500 and never a Claesen fallback.
 *  - /contact-email fails closed for a named site that does not resolve,
 *    like /consultations (EnsureRequestedSiteResolved).
 *  - The intake idempotency cache is scoped per site: replaying a key from
 *    another site never returns the first site's lead.
 */
final class PublicSiteHardeningTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => []]);
        });

        Http::fake();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedSelectors(): array
    {
        return [
            'array' => ['site%5B%5D=electro-bertels'],
            'empty array item' => ['site%5B%5D='],
            'keyed array' => ['site%5Bkey%5D=x'],
        ];
    }

    #[DataProvider('malformedSelectors')]
    public function test_a_non_string_site_is_a_400_on_a_public_read(string $query): void
    {
        $this->getJson('/v1/website/projects?'.$query)
            ->assertStatus(400)
            ->assertExactJson(['message' => 'Invalid site parameter.']);
    }

    #[DataProvider('malformedSelectors')]
    public function test_a_non_string_site_is_a_400_on_both_intakes_and_stores_nothing(string $query): void
    {
        $this->bertelsFixtureSite();

        $this->postJson('/v1/website/consultations?'.$query, $this->payload())
            ->assertStatus(400)
            ->assertExactJson(['message' => 'Invalid site parameter.']);
        $this->postJson('/v1/website/contact-email?'.$query, $this->payload())
            ->assertStatus(400);

        $this->assertSame(0, $this->leads()->count());
    }

    public function test_contact_email_rejects_a_named_site_that_is_not_active(): void
    {
        $site = $this->bertelsFixtureSite();
        $site->update(['status' => Site::STATUS_SUSPENDED]);

        foreach (['site='.$site->key, 'site=no-such-site', 'site='] as $query) {
            $this->postJson('/v1/website/contact-email?'.$query, $this->payload())
                ->assertStatus(503)
                ->assertExactJson(['message' => 'Intake temporarily unavailable.']);
        }

        $this->assertSame(0, $this->leads()->count());
    }

    public function test_contact_email_stores_the_lead_under_an_active_named_site(): void
    {
        $site = $this->bertelsFixtureSite();

        $this->postJson('/v1/website/contact-email?site='.$site->key, $this->payload())
            ->assertCreated();

        $this->assertSame([$site->id], $this->leads()->pluck('site_id')->all());
    }

    public function test_an_idempotency_key_replayed_from_another_site_never_returns_the_first_sites_lead(): void
    {
        $bertels = $this->bertelsFixtureSite();
        $headers = ['Idempotency-Key' => 'cla-706-shared-key'];

        $claesen = $this->postJson('/v1/website/consultations', $this->payload(['email' => 'first@example.test']), $headers)
            ->assertCreated();
        $other = $this->postJson('/v1/website/consultations?site='.$bertels->key, $this->payload(['email' => 'second@example.test']), $headers)
            ->assertCreated();

        $this->assertNotSame($claesen->json('data.id'), $other->json('data.id'));
        $this->assertSame('second@example.test', $other->json('data.email'));
        $this->assertSame($bertels->id, $other->json('data.site_id'));
        $this->assertStringNotContainsString('first@example.test', $other->getContent());

        $stored = ConsultationRequest::query()->withoutGlobalScopes()
            ->whereIn('email', ['first@example.test', 'second@example.test'])
            ->pluck('site_id', 'email')->all();
        $this->assertSame(['first@example.test' => Site::claesenId(), 'second@example.test' => $bertels->id], $stored);
    }

    /**
     * PHP drops leading spaces from parameter names, so ` site` is a second
     * `site` selector even though it is not spelled the same on the wire.
     */
    public function test_repeated_selectors_are_rejected_on_both_intakes_including_php_normalized_names(): void
    {
        $site = $this->bertelsFixtureSite();

        foreach (['/v1/website/consultations', '/v1/website/contact-email'] as $uri) {
            foreach ([
                'site=unknown&site='.$site->key,
                'site=unknown&%20site='.$site->key,
                'site=unknown&+site='.$site->key,
                'site=unknown&site%00suffix='.$site->key,
            ] as $query) {
                $this->postJson($uri.'?'.$query, $this->payload())->assertStatus(503);
            }
        }

        $this->assertSame(0, $this->leads()->count());
    }

    public function test_an_idempotency_key_of_zero_is_still_a_key(): void
    {
        $headers = ['Idempotency-Key' => '0'];

        $first = $this->postJson('/v1/website/consultations', $this->payload(), $headers)->assertCreated();
        $again = $this->postJson('/v1/website/consultations', $this->payload(), $headers)->assertCreated();

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame(1, $this->leads()->count());
    }

    public function test_a_site_sent_in_the_body_is_ignored(): void
    {
        $bertels = $this->bertelsFixtureSite();

        $this->postJson('/v1/website/consultations', $this->payload(['site' => $bertels->key]))->assertCreated();

        $this->assertSame([Site::claesenId()], $this->leads()->pluck('site_id')->all());
    }

    public function test_an_idempotency_key_replayed_on_the_same_site_still_stores_one_lead(): void
    {
        $bertels = $this->bertelsFixtureSite();
        $headers = ['Idempotency-Key' => 'cla-706-same-site'];

        $first = $this->postJson('/v1/website/consultations?site='.$bertels->key, $this->payload(), $headers)->assertCreated();
        $again = $this->postJson('/v1/website/consultations?site='.$bertels->key, $this->payload(), $headers)->assertCreated();

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame(1, $this->leads()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hardening Visitor',
            'email' => 'hardening-visitor@example.test',
            'message' => 'Graag meer info.',
        ], $overrides);
    }

    private function leads()
    {
        return ConsultationRequest::query()->withoutGlobalScopes()->where('email', 'hardening-visitor@example.test');
    }
}
