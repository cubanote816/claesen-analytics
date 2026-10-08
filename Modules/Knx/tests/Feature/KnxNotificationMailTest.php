<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Mail\KnxNoticeMail;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxNotificationPreference;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * KNX-54 — who gets told what, and when (preferences + email).
 *
 * The decisions: immediate mail only for `issue_reported`, `malfunction_reported` and a
 * `final` visit closure; the rest in a digest; recipients are the project lead plus the
 * office mailbox; and a field retry never duplicates a mail.
 */
final class KnxNotificationMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Organization::factory()->create(['slug' => 'electro-bertels']);
        $this->seed(KnxDemoSeeder::class);
    }

    private function office(): User
    {
        return User::query()->where('email', 'lien.smet@electrobertels.be')->sole();
    }

    private function notice(string $kind, array $overrides = []): KnxNotification
    {
        $project = KnxProject::query()->where('code', 'C1618')->sole();
        $actor = KnxEmployee::query()->where('name', 'Jan Van Dyck')->sole();

        return KnxNotification::create(array_merge([
            'project_id' => $project->getKey(),
            'kind' => $kind,
            'title' => 'Testmelding',
            'summary' => 'test',
            'reported_by_employee_id' => $actor->getKey(),
            'reported_at' => now(),
        ], $overrides));
    }

    public function test_the_preferences_default_and_round_trip(): void
    {
        $this->actingAs($this->office(), 'sanctum');

        $this->getJson('/api/v1/knx/me/notification-preferences')->assertOk()
            ->assertJsonPath('email.immediate', true)
            ->assertJsonPath('email.digest', 'per_visit')
            ->assertJsonPath('email.mutedKinds', []);

        $saved = $this->putJson('/api/v1/knx/me/notification-preferences', [
            'email' => ['immediate' => false, 'digest' => 'hourly', 'mutedKinds' => ['module_added']],
        ])->assertOk();

        $this->assertSame('hourly', $saved->json('email.digest'));
        $this->assertFalse($saved->json('email.immediate'));
        $this->assertSame(['module_added'], $saved->json('email.mutedKinds'));

        // Unknown digest and unknown kind are field errors.
        $this->putJson('/api/v1/knx/me/notification-preferences', ['email' => ['immediate' => true, 'digest' => 'daily']])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['email.digest']]);
        $this->putJson('/api/v1/knx/me/notification-preferences', ['email' => ['immediate' => true, 'digest' => 'off', 'mutedKinds' => ['nope']]])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['email.mutedKinds.0']]);

        // Preferences are personal: a field token cannot reach the office route.
        $this->actingAs(User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(), 'sanctum');
        $this->getJson('/api/v1/knx/me/notification-preferences')->assertUnauthorized();
    }

    public function test_an_immediate_kind_is_mailed_when_it_happens(): void
    {
        Mail::fake();

        $notice = $this->notice(KnxNotification::KIND_ISSUE_REPORTED);

        Mail::assertQueued(KnxNoticeMail::class, 1);
        $this->assertNotNull($notice->refresh()->emailed_at, 'the immediate notice is marked as emailed');
    }

    public function test_a_non_immediate_kind_waits_for_the_digest_and_is_never_mailed_twice(): void
    {
        Mail::fake();

        $notice = $this->notice(KnxNotification::KIND_BOARD_CREATED);

        // Nothing goes out on creation.
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertNull($notice->refresh()->emailed_at);

        // The digest picks it up. It also carries the fixture's own pending notices, so
        // what is asserted is that MY notice was covered and marked, and that a second
        // run changes nothing.
        $this->artisan('knx:notification-digest')->assertSuccessful();
        $this->assertNotNull($notice->refresh()->emailed_at);
        $this->assertGreaterThan(0, Mail::queued(KnxNoticeMail::class)->count());

        // ...and a second run sends nothing: `emailed_at` is the dedupe flag.
        $queuedAfterFirstRun = Mail::queued(KnxNoticeMail::class)->count();
        $this->artisan('knx:notification-digest')->assertSuccessful();
        $this->assertSame($queuedAfterFirstRun, Mail::queued(KnxNoticeMail::class)->count());
    }

    public function test_the_office_mailbox_always_receives_and_the_lead_can_opt_out(): void
    {
        Mail::fake();
        config(['knx.office_mailbox' => 'kantoor@electrobertels.be']);

        $lead = KnxEmployee::query()->where('name', 'Lien Smet')->sole();
        $leadUser = User::query()->where('email', 'lien.smet@electrobertels.be')->sole();

        // The lead turns the immediate mail off: the shared box still gets it.
        $this->actingAs($leadUser, 'sanctum');
        $this->putJson('/api/v1/knx/me/notification-preferences', [
            'email' => ['immediate' => false, 'digest' => 'per_visit', 'mutedKinds' => []],
        ])->assertOk();

        $this->notice(KnxNotification::KIND_MALFUNCTION_REPORTED);

        Mail::assertQueued(KnxNoticeMail::class, function (KnxNoticeMail $mail): bool {
            return $mail->hasTo('kantoor@electrobertels.be');
        });
        Mail::assertNotQueued(KnxNoticeMail::class, function (KnxNoticeMail $mail): bool {
            return $mail->hasTo('lien.smet@electrobertels.be');
        });

        // Sanity: the lead row was the one changed.
        $this->assertFalse(
            KnxNotificationPreference::forEmployee($lead)->email_immediate,
        );
    }
}
