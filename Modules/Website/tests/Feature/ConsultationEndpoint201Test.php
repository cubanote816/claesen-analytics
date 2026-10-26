<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Http;
use Modules\Core\Models\Site;
use Modules\Website\Models\ConsultationEmailDelivery;
use Modules\Website\Models\ConsultationRequest;
use Tests\TestCase;

/**
 * CLA-532: POST /v1/website/consultations must return exactly HTTP 201 and
 * persist exactly one row per request even when the microsoft-graph mailer is
 * unconfigured or when website.consultation_notification_email is null. The
 * notification is sent in DB::afterCommit; a failure there is logged, never a
 * 500 after the row is already committed. (Without an Idempotency-Key header
 * — this file never sends one — two deliberate POSTs create two rows; the
 * guarantee is only "each 201 == one row, no post-commit 500". CLA-478 added
 * opt-in idempotency via that header; see
 * Modules/Website/tests/Feature/LeadIntakeConsolidationTest.php for that
 * behaviour.)
 *
 * DatabaseTruncation (like ConsultationEmailTest) so DB::afterCommit actually
 * fires. Authored here; executed in CI (no local MySQL).
 */
class ConsultationEndpoint201Test extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();
    }

    private function payload(): array
    {
        return [
            'name' => 'Jan Claesen',
            'email' => 'jan@example.com',
            'message' => 'Interested in stadium lighting.',
        ];
    }

    public function test_returns_201_and_one_row_when_graph_is_unconfigured(): void
    {
        config([
            'website.consultation_notification_email' => 'backoffice@example.test',
            'mail.mailers.microsoft-graph.client_id' => null,
            'mail.mailers.microsoft-graph.tenant_id' => null,
            'mail.mailers.microsoft-graph.client_secret' => null,
        ]);

        $before = ConsultationRequest::count();

        $this->postJson('/v1/website/consultations', $this->payload())
            ->assertStatus(201);

        $this->assertSame($before + 1, ConsultationRequest::count());
        Http::assertNothingSent();
    }

    public function test_returns_201_and_skips_notification_when_recipient_is_null(): void
    {
        // "Null" now has to mean BOTH sources: the site's own recipient wins over config, and
        // every site has one. Without clearing the site's too, this test ran a scenario where
        // the notice was NOT skipped and never said so — it only counted rows, so its name
        // promised more than its body did. `sites` is excluded from DatabaseTruncation
        // (tests/TestCase.php), so the seeded value is put back.
        $site = Site::query()->where('key', 'claesen-verlichting')->firstOrFail();
        $seededRecipient = $site->mail_notification_email;
        $site->update(['mail_notification_email' => null]);
        config(['website.consultation_notification_email' => null]);

        try {
            $before = ConsultationRequest::count();

            $this->postJson('/v1/website/consultations', $this->payload())
                ->assertStatus(201);

            $this->assertSame($before + 1, ConsultationRequest::count());
            Http::assertNothingSent();

            // What CLA-532 actually promises: the lead is kept and the notice is skipped,
            // never a hard failure. Asserting it is the point of this test's name.
            $this->assertSame(0, ConsultationEmailDelivery::query()
                ->where('type', ConsultationEmailDelivery::TYPE_INTERNAL)
                ->count());
        } finally {
            $site->update(['mail_notification_email' => $seededRecipient]);
        }
    }

    public function test_two_deliberate_posts_each_return_201_and_persist_one_row_each(): void
    {
        config(['website.consultation_notification_email' => null]);

        $this->postJson('/v1/website/consultations', $this->payload())->assertStatus(201);
        $this->postJson('/v1/website/consultations', $this->payload())->assertStatus(201);

        // The endpoint is not idempotent: two submissions => two rows. What this
        // asserts is that the second 201 is a real success, not a client retry
        // forced by a post-commit 500 on the first.
        $this->assertSame(2, ConsultationRequest::where('email', 'jan@example.com')->count());
        Http::assertNothingSent();
    }
}
