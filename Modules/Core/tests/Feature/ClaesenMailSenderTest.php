<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Core\Notifications\AccountActivationNotification;
use Modules\Core\Notifications\PasswordResetNotification;
use Modules\Core\Notifications\VerifyEmailAuthenticationWithBrand;
use Modules\Core\Tests\Support\InteractsWithOrganizationFixtures;
use Modules\Performance\Emails\ImmediateRiskAlertMail;
use Modules\Performance\Emails\VanguardImmediateAlertMail;
use Modules\Performance\Emails\WatchdogRiskReportMail;
use Modules\Performance\Models\ProjectInsight;
use Modules\Safety\Emails\InspectionReminderMail;
use Modules\Safety\Emails\InspectionReportMail;
use Modules\Safety\Models\Inspection;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Contracts\Queue\Job;
use Modules\Core\Support\MailBrand;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * CLA-699: Claesen automatic mail leaves from the platform sender
 * (`mail.from.*`, backoffice@ in production) and says it is not monitored; the
 * quote mails keep the Claesen SITE sender (offerte@); Electro Bertels is untouched.
 */
final class ClaesenMailSenderTest extends TestCase
{
    use InteractsWithOrganizationFixtures;
    use RefreshDatabase;

    private const EN = 'This is an automated message. Please do not reply to this email; this mailbox is not monitored.';

    private const NL = 'Dit is een automatisch bericht. Beantwoord deze e-mail niet; deze mailbox wordt niet gelezen.';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.from.address' => 'backoffice@claesen-verlichting.test',
            'mail.from.name' => 'Platform Sender',
        ]);
    }

    /** @return array<string, array{0: callable(): Notification}> */
    public static function notifications(): array
    {
        return [
            'sign-in code' => [fn () => new VerifyEmailAuthenticationWithBrand('123456', 5)],
            'password reset' => [fn () => new PasswordResetNotification('a-code')],
            'account activation' => [fn () => new AccountActivationNotification('https://example.test/activate')],
        ];
    }

    private function lastEmail(): Email
    {
        $sent = collect(Mail::mailer()->getSymfonyTransport()->messages())->last();
        $this->assertInstanceOf(SentMessage::class, $sent);
        $email = $sent->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $email);

        return $email;
    }

    private function sendNotification(User $user, Notification $notification): Email
    {
        NotificationFacade::sendNow($user, $notification);

        return $this->lastEmail();
    }

    private function sendMailable(Mailable $mailable, string|User $to = 'someone@example.test'): Email
    {
        Mail::to($to)->send($mailable);

        return $this->lastEmail();
    }

    private function assertNotice(Email $email, string $notice): void
    {
        $this->assertStringContainsString($notice, (string) $email->getHtmlBody());
    }

    private function assertNoNotice(Email $email): void
    {
        foreach ([self::EN, self::NL] as $notice) {
            $this->assertStringNotContainsString($notice, (string) $email->getHtmlBody());
            $this->assertStringNotContainsString($notice, (string) $email->getTextBody());
        }
    }

    #[DataProvider('notifications')]
    public function test_claesen_automatic_notifications_use_the_platform_sender_and_the_english_notice(callable $make): void
    {
        $email = $this->sendNotification($this->claesenUser(['language' => 'en']), $make());

        $this->assertSame('backoffice@claesen-verlichting.test', $email->getFrom()[0]->getAddress());
        $this->assertNotice($email, self::EN);
    }

    #[DataProvider('notifications')]
    public function test_a_dutch_claesen_recipient_gets_the_dutch_notice(callable $make): void
    {
        $email = $this->sendNotification($this->claesenUser(['language' => 'nl']), $make());

        $this->assertSame('backoffice@claesen-verlichting.test', $email->getFrom()[0]->getAddress());
        $this->assertNotice($email, self::NL);
        $this->assertStringNotContainsString(self::EN, (string) $email->getHtmlBody());
    }

    public function test_the_claesen_site_sender_does_not_leak_into_automatic_mail(): void
    {
        Site::query()->where('key', config('organizations.panel_sites.admin'))
            ->update(['mail_from_address' => 'offerte@claesen-verlichting.test']);

        $email = $this->sendNotification($this->claesenUser(['language' => 'en']), new PasswordResetNotification('c'));

        $this->assertSame('backoffice@claesen-verlichting.test', $email->getFrom()[0]->getAddress());
    }

    #[DataProvider('notifications')]
    public function test_bertels_notifications_keep_the_bertels_sender_and_have_no_notice(callable $make): void
    {
        $organization = $this->bertelsFixtureOrganization();
        $this->bertelsFixtureSite($organization, [
            'mail_from_address' => 'info@electrobertels.test',
            'mail_from_name' => 'Electro Bertels',
        ]);

        $email = $this->sendNotification($this->bertelsFixtureUser($organization, ['language' => 'nl']), $make());

        $this->assertSame('info@electrobertels.test', $email->getFrom()[0]->getAddress());
        $this->assertSame('Electro Bertels', $email->getFrom()[0]->getName());
        $this->assertNoNotice($email);
    }

    public function test_a_notice_does_not_leak_from_a_claesen_send_into_the_next_bertels_send(): void
    {
        $this->sendNotification($this->claesenUser(['language' => 'en']), new PasswordResetNotification('c'));

        $organization = $this->bertelsFixtureOrganization();
        $this->bertelsFixtureSite($organization);
        $email = $this->sendNotification($this->bertelsFixtureUser($organization), new PasswordResetNotification('c'));

        $this->assertNoNotice($email);
    }

    public function test_the_migration_gives_the_claesen_site_the_quote_sender_and_leaves_bertels_alone(): void
    {
        $key = config('organizations.panel_sites.admin');
        $migration = require base_path('Modules/Core/database/migrations/2026_11_17_100000_set_claesen_quote_mail_sender.php');

        $this->bertelsFixtureSite(null, ['key' => 'electro-bertels', 'mail_from_address' => 'info@electrobertels.be']);

        $migration->down();
        $this->assertNull(DB::table('sites')->where('key', $key)->value('mail_from_address'));

        $migration->up();
        $this->assertSame('offerte@claesen-verlichting.be', DB::table('sites')->where('key', $key)->value('mail_from_address'));
        $this->assertSame('info@electrobertels.be', DB::table('sites')->where('key', 'electro-bertels')->value('mail_from_address'));

        $migration->down();
        $this->assertNull(DB::table('sites')->where('key', $key)->value('mail_from_address'));
        $this->assertSame('info@electrobertels.be', DB::table('sites')->where('key', 'electro-bertels')->value('mail_from_address'));
    }

    public function test_the_migration_is_a_no_op_when_the_claesen_site_row_is_missing(): void
    {
        $migration = require base_path('Modules/Core/database/migrations/2026_11_17_100000_set_claesen_quote_mail_sender.php');
        $before = DB::table('sites')->get()->map(fn ($r) => (array) $r)->all();

        // The migration resolves the row by the configured admin site key: a key with no row.
        config(['organizations.panel_sites.admin' => 'no-such-site']);
        $migration->up();
        $migration->down();

        $this->assertSame($before, DB::table('sites')->get()->map(fn ($r) => (array) $r)->all());
    }

    /** @return array<string, array{0: callable(): Mailable}> */
    public static function fourMails(): array
    {
        $insight = fn () => new ProjectInsight(['project_id' => 7, 'full_dna' => ['name' => 'P']]);

        return [
            'inspection reminder' => [fn () => new InspectionReminderMail(User::factory()->make(['name' => 'Jan']), 3)],
            'immediate risk alert' => [fn () => new ImmediateRiskAlertMail($insight(), 25000.0)],
            'watchdog report' => [fn () => new WatchdogRiskReportMail('plain report')],
            'vanguard alert' => [fn () => new VanguardImmediateAlertMail(['id' => 7, 'name' => 'P', 'wip' => 25000, 'stale_days' => 3, 'risk_level' => 'CRITICAL'])],
        ];
    }

    #[DataProvider('fourMails')]
    public function test_the_four_mails_use_the_platform_sender_and_carry_the_notice(callable $make): void
    {
        $email = $this->sendMailable($make(), 'dest@example.test');

        $this->assertSame('backoffice@claesen-verlichting.test', $email->getFrom()[0]->getAddress());
        $this->assertSame('Platform Sender', $email->getFrom()[0]->getName());
        $this->assertSame('dest@example.test', $email->getTo()[0]->getAddress());
        $this->assertNotice($email, self::EN);
    }

    #[DataProvider('fourMails')]
    public function test_the_four_mails_render_the_dutch_notice_for_a_dutch_recipient(callable $make): void
    {
        $email = $this->sendMailable($make()->locale('nl'), 'dest@example.test');

        $this->assertNotice($email, self::NL);
    }

    public function test_the_performance_alerts_keep_their_no_reply_reply_to(): void
    {
        $insight = new ProjectInsight(['project_id' => 7, 'full_dna' => ['name' => 'P']]);

        foreach ([new ImmediateRiskAlertMail($insight, 25000.0), new WatchdogRiskReportMail('r')] as $mailable) {
            $email = $this->sendMailable($mailable);
            $this->assertSame('no-reply@claesen-verlichting.be', $email->getReplyTo()[0]->getAddress());
        }

        $this->assertSame([], $this->sendMailable(new VanguardImmediateAlertMail(['id' => 1, 'name' => 'P', 'wip' => 1, 'stale_days' => 1, 'risk_level' => 'HIGH']))->getReplyTo());
    }

    public function test_a_claesen_user_gets_the_platform_sender_name_not_the_site_name(): void
    {
        Site::query()->where('key', config('organizations.panel_sites.admin'))
            ->update(['mail_from_name' => 'Quote Sender']);

        $email = $this->sendNotification($this->claesenUser(['language' => 'en']), new PasswordResetNotification('c'));

        $this->assertSame('backoffice@claesen-verlichting.test', $email->getFrom()[0]->getAddress());
        $this->assertSame('Platform Sender', $email->getFrom()[0]->getName());
    }

    public function test_a_bertels_user_without_a_site_gets_no_notice(): void
    {
        $email = $this->sendNotification($this->bertelsFixtureUser(null, ['language' => 'en']), new PasswordResetNotification('c'));

        $this->assertNoNotice($email);
        // S6: suppressing the notice must not switch this mail to the Bertels theme;
        // a no-site user kept the default (Claesen) logo before CLA-699.
        $this->assertStringContainsString('cid:claesen-logo@mail.brand', (string) $email->getHtmlBody());
    }

    public function test_a_user_without_organization_still_gets_the_notice(): void
    {
        $email = $this->sendNotification(User::factory()->create(['organization_id' => null, 'language' => 'en']), new PasswordResetNotification('c'));

        $this->assertNotice($email, self::EN);
    }

    public function test_a_starting_queue_job_clears_stale_quote_state(): void
    {
        $site = Site::query()->where('key', config('organizations.panel_sites.admin'))->firstOrFail();
        MailBrand::forSite($site);
        $this->assertFalse(MailBrand::showsAutomatedNotice());

        event(new JobProcessing('sync', $this->createMock(Job::class)));

        $this->assertTrue(MailBrand::showsAutomatedNotice());
        $this->assertStringContainsString(self::EN, (string) (new MailMessage)->line('Hello')->render());
    }

    public function test_the_inspection_report_mail_carries_the_notice(): void
    {
        foreach (['super_admin', 'admin', 'project_manager', 'viewer'] as $role) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $inspection = \Modules\Safety\Database\Factories\InspectionFactory::new()->create(['pdf_path' => 'x/report.pdf']);
        $html = (new InspectionReportMail($inspection, User::factory()->create()))->render();

        $this->assertStringContainsString(self::EN, $html);
    }
}
