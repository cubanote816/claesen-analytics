<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Modules\Website\Models\ConsultationRequest;
use Modules\Website\Models\WebsiteIntakeSpamAttempt;
use Tests\TestCase;

/**
 * F4/CLA-475 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Covers Modules\Website\Services\IntakeSpamGuard and the field-length/
 * consent additions to the public intake endpoints. The per-IP rate limit
 * itself is exercised end-to-end in
 * tests/Feature/ClaesenBaseline/WebsitePublicApiContractTest.php
 * (test_the_public_intake_endpoints_are_now_rate_limited_by_ip) — this file
 * only confirms the middleware is actually attached, cheaply, without
 * spending config('...max_attempts') real HTTP requests per test.
 *
 * Every assertion is scoped by a unique per-test e-mail rather than an
 * absolute ConsultationRequest::count() — the same pollution CLA-478 already
 * documented and fixed for LeadIntakeConsolidationTest/ConsultationEndpoint201Test:
 * the DatabaseTruncation-based classes earlier in a full-suite run (e.g.
 * ConsultationEmailTest, MigrationJsonConversionTest) commit real rows that
 * outlive their own class, so any later RefreshDatabase test's baseline count
 * is not reliably zero.
 */
final class IntakeHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jan Janssens',
            'email' => 'jan-'.uniqid().'@example.test',
            'message' => 'We need new floodlights for our main pitch.',
        ], $overrides);
    }

    private function forEmail(string $email)
    {
        return ConsultationRequest::query()->where('email', $email);
    }

    // -------------------------------------------------------------------------
    // Field length
    // -------------------------------------------------------------------------

    public function test_message_over_the_configured_max_length_is_rejected(): void
    {
        $email = 'jan-'.uniqid().'@example.test';
        $tooLong = str_repeat('a', config('website.intake_hardening.message_max_length', 5000) + 1);

        $this->postJson('/v1/website/consultations', $this->validPayload(['email' => $email, 'message' => $tooLong]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->assertSame(0, $this->forEmail($email)->count());
    }

    public function test_message_at_exactly_the_configured_max_length_is_accepted(): void
    {
        $email = 'jan-'.uniqid().'@example.test';
        $atLimit = str_repeat('a', config('website.intake_hardening.message_max_length', 5000));

        $this->postJson('/v1/website/consultations', $this->validPayload(['email' => $email, 'message' => $atLimit]))
            ->assertCreated();

        $this->assertSame(1, $this->forEmail($email)->count());
    }

    // -------------------------------------------------------------------------
    // Honeypot
    // -------------------------------------------------------------------------

    public function test_honeypot_field_filled_silently_drops_the_consultation_submission(): void
    {
        $email = 'jan-'.uniqid().'@example.test';
        $honeypotField = config('website.intake_hardening.honeypot_field');

        $this->postJson('/v1/website/consultations', $this->validPayload(['email' => $email, $honeypotField => 'I am a bot']))
            ->assertCreated();

        $this->assertSame(0, $this->forEmail($email)->count());
        $this->assertSame(1, WebsiteIntakeSpamAttempt::query()->where('reason', WebsiteIntakeSpamAttempt::REASON_HONEYPOT)->count());
    }

    public function test_honeypot_field_filled_silently_drops_the_contact_email_submission(): void
    {
        $email = 'jan-'.uniqid().'@example.test';
        $honeypotField = config('website.intake_hardening.honeypot_field');

        $this->postJson('/v1/website/contact-email', $this->validPayload(['email' => $email, $honeypotField => 'I am a bot']))
            ->assertCreated()
            ->assertJson(['success' => true]);

        $this->assertSame(0, $this->forEmail($email)->count());
        $this->assertSame(1, WebsiteIntakeSpamAttempt::query()->where('reason', WebsiteIntakeSpamAttempt::REASON_HONEYPOT)->count());
    }

    public function test_an_empty_honeypot_field_never_blocks_a_real_visitor(): void
    {
        $email = 'jan-'.uniqid().'@example.test';
        $honeypotField = config('website.intake_hardening.honeypot_field');

        $this->postJson('/v1/website/consultations', $this->validPayload(['email' => $email, $honeypotField => '']))
            ->assertCreated();

        $this->assertSame(1, $this->forEmail($email)->count());
        $this->assertSame(0, WebsiteIntakeSpamAttempt::query()->count());
    }

    public function test_the_honeypot_field_name_is_read_from_config_not_hardcoded(): void
    {
        config(['website.intake_hardening.honeypot_field' => 'custom_trap_field']);

        $emailOne = 'jan-'.uniqid().'@example.test';
        $emailTwo = 'jan-'.uniqid().'@example.test';

        // Filling the OLD default field name now does nothing — it isn't
        // the configured honeypot anymore, and the controller's dynamic
        // rule key means it isn't even a valid known field.
        $this->postJson('/v1/website/consultations', $this->validPayload(['email' => $emailOne, 'website_url' => 'not a bot signal anymore']))
            ->assertCreated();
        $this->assertSame(1, $this->forEmail($emailOne)->count());

        // Filling the NEW configured field name is what triggers the drop.
        $this->postJson('/v1/website/consultations', $this->validPayload(['email' => $emailTwo, 'custom_trap_field' => 'I am a bot']))
            ->assertCreated();
        $this->assertSame(0, $this->forEmail($emailTwo)->count());
        $this->assertSame(1, WebsiteIntakeSpamAttempt::query()->where('reason', WebsiteIntakeSpamAttempt::REASON_HONEYPOT)->count());
    }

    // -------------------------------------------------------------------------
    // Turnstile — inert by default (D4-style two-step activation)
    // -------------------------------------------------------------------------

    public function test_turnstile_is_inert_by_default_no_token_required(): void
    {
        $email = 'jan-'.uniqid().'@example.test';
        Http::fake();

        $this->postJson('/v1/website/consultations', $this->validPayload(['email' => $email]))
            ->assertCreated();

        $this->assertSame(1, $this->forEmail($email)->count());
        Http::assertNothingSent();
    }

    public function test_turnstile_configured_but_not_enforced_still_never_calls_cloudflare(): void
    {
        config(['services.turnstile.secret_key' => 'fake-secret', 'services.turnstile.enforce' => false]);
        Http::fake();

        $this->postJson('/v1/website/consultations', $this->validPayload())
            ->assertCreated();

        Http::assertNothingSent();
    }

    public function test_turnstile_enforced_and_verified_allows_the_submission(): void
    {
        $email = 'jan-'.uniqid().'@example.test';
        config(['services.turnstile.secret_key' => 'fake-secret', 'services.turnstile.enforce' => true]);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $this->postJson('/v1/website/consultations', $this->validPayload(['email' => $email, 'turnstile_token' => 'valid-token']))
            ->assertCreated();

        $this->assertSame(1, $this->forEmail($email)->count());

        Http::assertSent(fn ($request) => $request->url() === config('services.turnstile.verify_url')
            && $request['secret'] === 'fake-secret'
            && $request['response'] === 'valid-token');
    }

    public function test_turnstile_enforced_and_failed_verification_is_rejected(): void
    {
        $email = 'jan-'.uniqid().'@example.test';
        config(['services.turnstile.secret_key' => 'fake-secret', 'services.turnstile.enforce' => true]);
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        $this->postJson('/v1/website/consultations', $this->validPayload(['email' => $email, 'turnstile_token' => 'bad-token']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('turnstile_token');

        $this->assertSame(0, $this->forEmail($email)->count());
        $this->assertSame(1, WebsiteIntakeSpamAttempt::query()->where('reason', WebsiteIntakeSpamAttempt::REASON_TURNSTILE_FAILED)->count());
    }

    public function test_turnstile_enforced_and_missing_token_is_rejected_without_a_network_call(): void
    {
        $email = 'jan-'.uniqid().'@example.test';
        config(['services.turnstile.secret_key' => 'fake-secret', 'services.turnstile.enforce' => true]);
        Http::fake();

        $this->postJson('/v1/website/consultations', $this->validPayload(['email' => $email]))
            ->assertStatus(422);

        $this->assertSame(0, $this->forEmail($email)->count());
        Http::assertNothingSent();
    }

    public function test_turnstile_verification_network_failure_fails_open(): void
    {
        $email = 'jan-'.uniqid().'@example.test';
        config(['services.turnstile.secret_key' => 'fake-secret', 'services.turnstile.enforce' => true]);
        Http::fake(function () {
            throw new ConnectionException('Cloudflare is unreachable.');
        });

        $this->postJson('/v1/website/consultations', $this->validPayload(['email' => $email, 'turnstile_token' => 'any-token']))
            ->assertCreated();

        $this->assertSame(1, $this->forEmail($email)->count());
        // A network failure is not evidence of spam — nothing recorded.
        $this->assertSame(0, WebsiteIntakeSpamAttempt::query()->count());
    }

    // -------------------------------------------------------------------------
    // Consent
    // -------------------------------------------------------------------------

    public function test_consent_is_stored_when_provided(): void
    {
        $email = 'jan-'.uniqid().'@example.test';

        $this->postJson('/v1/website/consultations', $this->validPayload([
            'email' => $email,
            'consent' => true,
            'policy_version' => '2026-09-v2',
        ]))->assertCreated();

        $consultation = $this->forEmail($email)->firstOrFail();

        $this->assertNotNull($consultation->consent_given_at);
        $this->assertSame('2026-09-v2', $consultation->consent_policy_version);
    }

    public function test_consent_is_optional_and_omitting_it_still_succeeds(): void
    {
        $email = 'jan-'.uniqid().'@example.test';

        $this->postJson('/v1/website/consultations', $this->validPayload(['email' => $email]))
            ->assertCreated();

        $consultation = $this->forEmail($email)->firstOrFail();

        $this->assertNull($consultation->consent_given_at);
        $this->assertNull($consultation->consent_policy_version);
    }

    public function test_consent_false_never_stores_a_policy_version_even_if_one_is_sent(): void
    {
        $email = 'jan-'.uniqid().'@example.test';

        $this->postJson('/v1/website/consultations', $this->validPayload([
            'email' => $email,
            'consent' => false,
            'policy_version' => '2026-09-v2',
        ]))->assertCreated();

        $consultation = $this->forEmail($email)->firstOrFail();

        $this->assertNull($consultation->consent_given_at);
        $this->assertNull($consultation->consent_policy_version);
    }

    // -------------------------------------------------------------------------
    // Rate limit middleware presence (cheap check, see class docblock)
    // -------------------------------------------------------------------------

    public function test_both_public_intake_routes_carry_the_throttle_middleware(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route) => in_array($route->uri(), ['v1/website/consultations', 'v1/website/contact-email'], true));

        $this->assertCount(2, $routes);

        foreach ($routes as $route) {
            $this->assertTrue(
                collect($route->middleware())->contains(fn ($m) => str_starts_with($m, 'throttle:')),
                "Route {$route->uri()} is missing the intake throttle middleware."
            );
        }
    }
}
