<?php

namespace Modules\Website\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Website\Models\WebsiteIntakeSpamAttempt;

/**
 * F4/CLA-475 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Called from both public intake controllers (ConsultationController,
 * ContactController) before Modules\Website\Services\ConsultationService::
 * handlePublicIntake() ever runs — a request this guard rejects/drops
 * never creates a ConsultationRequest, a Prospects lead, or an e-mail
 * delivery row.
 *
 * Two independent layers, deliberately not combined into one check:
 *   - Honeypot: always active, no configuration needed. A silent drop
 *     (fake 201, no record persisted) — never tips a bot off that it was
 *     caught, unlike a 422 would.
 *   - Turnstile: inert until BOTH services.turnstile.secret_key and
 *     services.turnstile.enforce are set (see config/services.php's own
 *     docblock) — a real 422 rejection when it does fire, since an
 *     enforced Turnstile failure is a case the visitor can plausibly
 *     retry (reload the widget), unlike the honeypot.
 *   - IP rate limiting is NOT this class's job — it's the
 *     `throttle:` route middleware in Modules/Website/Routes/api.php,
 *     which never reaches a controller at all on the limit, so there is
 *     nothing for this guard to record (a throttled request carries no
 *     evidence of spam intent, only volume the rate limiter already saw).
 */
class IntakeSpamGuard
{
    /**
     * True when the payload's honeypot field is non-empty. Never throws —
     * the caller decides what a "silent drop" response looks like, since
     * ConsultationController and ContactController don't share a response
     * shape.
     */
    public function isHoneypotTriggered(array $validated): bool
    {
        $field = config('website.intake_hardening.honeypot_field');

        return filled($validated[$field] ?? null);
    }

    /**
     * @throws ValidationException when Turnstile is configured AND enforced
     *                             AND verification fails or the token is
     *                             missing. A no-op (returns silently)
     *                             whenever either of those two conditions
     *                             is false, or when Cloudflare's endpoint
     *                             itself is unreachable — this guard never
     *                             lets a third-party outage block every
     *                             legitimate submission on the site
     *                             ("Turnstile accesible con fallback").
     */
    public function assertTurnstilePasses(?string $token, ?int $siteId, string $ip): void
    {
        if (! config('services.turnstile.enforce') || blank(config('services.turnstile.secret_key'))) {
            return;
        }

        if ($this->verifyWithCloudflare($token, $ip)) {
            return;
        }

        $this->recordAttempt($siteId, $ip, WebsiteIntakeSpamAttempt::REASON_TURNSTILE_FAILED);

        throw ValidationException::withMessages([
            'turnstile_token' => 'The anti-abuse challenge could not be verified. Please try again.',
        ]);
    }

    public function recordAttempt(?int $siteId, string $ip, string $reason): void
    {
        WebsiteIntakeSpamAttempt::create([
            'site_id' => $siteId,
            'ip' => $ip,
            'reason' => $reason,
        ]);
    }

    private function verifyWithCloudflare(?string $token, string $ip): bool
    {
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post(config('services.turnstile.verify_url'), [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]);

            return (bool) $response->json('success', false);
        } catch (\Throwable $e) {
            Log::warning('IntakeSpamGuard: Turnstile verification request failed — failing open.', [
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }
}
