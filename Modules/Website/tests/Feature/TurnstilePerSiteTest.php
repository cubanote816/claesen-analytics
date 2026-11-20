<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\Site;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\WebsiteIntakeSpamAttempt;
use Tests\TestCase;

/**
 * CLA-731: Cloudflare Turnstile is enforced PER SITE. The global switch
 * (services.turnstile.enforce + secret_key) stays the kill switch; only the
 * sites listed in website.intake_hardening.turnstile_required_site_keys
 * (Electro Bertels) need a token. Claesen's form sends none and keeps working.
 */
final class TurnstilePerSiteTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    private Site $bertels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(GeminiService::class, function ($mock): void {
            $mock->shouldReceive('translateAndDetect')->andReturn(['translations' => []]);
        });

        $this->bertels = $this->bertelsFixtureSite();

        config([
            'services.turnstile.secret_key' => 'test-secret',
            'services.turnstile.enforce' => true,
            'website.intake_hardening.turnstile_required_site_keys' => [$this->bertels->key],
        ]);
    }

    public function test_a_listed_site_without_a_token_is_rejected_on_both_intakes_without_a_network_call(): void
    {
        Http::fake();

        $this->postJson($this->consultations(), $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors('turnstile_token');
        $this->postJson($this->contactEmail(), $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['turnstile_token']]);

        $this->assertSame(0, $this->leads()->count());
        Http::assertNothingSent();
    }

    public function test_a_listed_site_with_a_verified_token_is_stored_and_the_visitor_ip_is_sent(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson($this->consultations(), $this->payload(['turnstile_token' => 'valid-token']))
            ->assertCreated();

        $this->assertSame([$this->bertels->id], $this->leads()->pluck('site_id')->all());
        Http::assertSent(fn ($request) => $request['secret'] === 'test-secret'
            && $request['response'] === 'valid-token'
            && $request['remoteip'] === '203.0.113.7');
    }

    public function test_a_failed_verification_is_rejected_and_recorded_under_the_site(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->postJson($this->consultations(), $this->payload(['turnstile_token' => 'bad-token']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('turnstile_token');

        $this->assertSame(0, $this->leads()->count());
        $this->assertSame(
            [$this->bertels->id],
            WebsiteIntakeSpamAttempt::query()->where('reason', WebsiteIntakeSpamAttempt::REASON_TURNSTILE_FAILED)->pluck('site_id')->all(),
        );
    }

    public function test_claesen_is_not_listed_and_needs_no_token_on_either_intake(): void
    {
        Http::fake();

        $this->postJson('/v1/website/consultations', $this->payload())->assertCreated();
        $this->postJson('/v1/website/contact-email', $this->payload(['email' => 'claesen-contact@example.test']))->assertCreated();

        // One lead per intake, both filed under Claesen.
        $this->assertSame([Site::claesenId(), Site::claesenId()], $this->leads()->orderBy('id')->pluck('site_id')->all());
        Http::assertNothingSent();
    }

    public function test_a_site_that_is_not_listed_needs_no_token(): void
    {
        config(['website.intake_hardening.turnstile_required_site_keys' => []]);
        Http::fake();

        $this->postJson($this->consultations(), $this->payload())->assertCreated();

        Http::assertNothingSent();
    }

    public function test_the_global_switch_off_disables_it_even_for_a_listed_site(): void
    {
        config(['services.turnstile.enforce' => false]);
        Http::fake();

        $this->postJson($this->consultations(), $this->payload())->assertCreated();

        Http::assertNothingSent();
    }

    public function test_a_cloudflare_outage_still_fails_open_for_a_listed_site(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Cloudflare is unreachable.');
        });

        $this->postJson($this->consultations(), $this->payload(['turnstile_token' => 'any-token']))->assertCreated();

        $this->assertSame(1, $this->leads()->count());
    }

    /**
     * Only a real `success: true` in a 2xx answer verifies. A string "false"
     * (truthy in PHP) and a 4xx are rejections, not passes.
     */
    public function test_only_a_boolean_success_in_a_2xx_answer_verifies(): void
    {
        foreach ([Http::response(['success' => 'false']), Http::response(['success' => true], 400), Http::response('not json')] as $answer) {
            Http::fake(['challenges.cloudflare.com/*' => $answer]);

            $this->postJson($this->consultations(), $this->payload(['turnstile_token' => 'some-token']))
                ->assertStatus(422)
                ->assertJsonValidationErrors('turnstile_token');
        }

        $this->assertSame(0, $this->leads()->count());
    }

    /** A Cloudflare outage answering 5xx fails open, like a connection failure. */
    public function test_a_cloudflare_5xx_fails_open(): void
    {
        Http::fake(['challenges.cloudflare.com/*' => Http::response('unavailable', 503)]);

        $this->postJson($this->consultations(), $this->payload(['turnstile_token' => 'any-token']))->assertCreated();

        $this->assertSame(0, WebsiteIntakeSpamAttempt::query()->count());
    }

    /**
     * Anything that is not "Cloudflare is unreachable" fails CLOSED, and the
     * log carries the exception class only, never its message (no token,
     * secret or URL).
     */
    public function test_an_unexpected_error_fails_closed_and_logs_only_the_exception_class(): void
    {
        Log::spy();
        Http::fake(function () {
            throw new \RuntimeException('boom with secret test-secret');
        });

        $this->postJson($this->consultations(), $this->payload(['turnstile_token' => 'any-token']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('turnstile_token');

        $this->assertSame(0, $this->leads()->count());
        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context = []): bool => ! str_contains($message.json_encode($context), 'test-secret')
                && ($context['exception'] ?? null) === \RuntimeException::class
        );
    }

    private function consultations(): string
    {
        return '/v1/website/consultations?site='.$this->bertels->key;
    }

    private function contactEmail(): string
    {
        return '/v1/website/contact-email?site='.$this->bertels->key;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Turnstile Visitor',
            'email' => 'turnstile-visitor@example.test',
            'message' => 'Graag meer info.',
        ], $overrides);
    }

    private function leads()
    {
        return ConsultationRequest::query()->withoutGlobalScopes()
            ->whereIn('email', ['turnstile-visitor@example.test', 'claesen-contact@example.test']);
    }
}
