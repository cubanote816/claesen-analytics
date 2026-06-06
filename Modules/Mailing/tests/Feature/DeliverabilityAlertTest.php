<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\DeliverabilityAlertType;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\DeliverabilityAlert;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Models\MessageEvent;
use Modules\Mailing\Notifications\DeliverabilityAlertNotification;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DeliverabilityAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function completedCampaign(int $daysAgo = 1): Campaign
    {
        return Campaign::factory()->create([
            'status'      => CampaignStatus::COMPLETED,
            'finished_at' => now()->subDays($daysAgo),
            'template_id' => EmailTemplate::factory()->create()->id,
        ]);
    }

    private function adminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        return $user;
    }

    /**
     * Creates N sent messages for a campaign and optionally attaches an event to some of them.
     */
    private function seedMessages(Campaign $campaign, int $sent, int $withEvent = 0, string $eventType = ''): void
    {
        $messages = CampaignMessage::factory()->sent()->count($sent)->create([
            'campaign_id' => $campaign->id,
        ]);

        if ($withEvent > 0 && $eventType) {
            $messages->take($withEvent)->each(function (CampaignMessage $msg) use ($eventType): void {
                MessageEvent::create([
                    'message_id'  => $msg->id,
                    'event_type'  => $eventType,
                    'occurred_at' => now(),
                ]);
            });
        }
    }

    // -------------------------------------------------------------------------
    // Healthy campaign — no alert
    // -------------------------------------------------------------------------

    public function test_no_alert_for_healthy_campaign(): void
    {
        Notification::fake();

        $this->completedCampaign();
        // No messages — or rates below threshold — nothing to alert

        $this->artisan('mailing:check-deliverability-alerts')->assertSuccessful();

        $this->assertDatabaseCount('mailing_deliverability_alerts', 0);
        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // Hard bounce threshold
    // -------------------------------------------------------------------------

    public function test_alert_created_for_high_hard_bounce_rate(): void
    {
        Notification::fake();
        $admin    = $this->adminUser();
        $campaign = $this->completedCampaign();

        // 10 sent, 1 hard bounce = 10% > 5% threshold
        $this->seedMessages($campaign, 10, 1, MessageEventType::BOUNCED_HARD->value);

        $this->artisan('mailing:check-deliverability-alerts')->assertSuccessful();

        $this->assertDatabaseHas('mailing_deliverability_alerts', [
            'campaign_id' => $campaign->id,
            'alert_type'  => DeliverabilityAlertType::HARD_BOUNCE_HIGH->value,
        ]);

        Notification::assertSentTo($admin, DeliverabilityAlertNotification::class);
    }

    // -------------------------------------------------------------------------
    // Spam complaint threshold
    // -------------------------------------------------------------------------

    public function test_alert_created_for_high_spam_rate(): void
    {
        Notification::fake();
        $admin    = $this->adminUser();
        $campaign = $this->completedCampaign();

        // 100 sent, 1 spam complaint = 1% > 0.08% threshold
        $this->seedMessages($campaign, 100, 1, MessageEventType::COMPLAINED->value);

        $this->artisan('mailing:check-deliverability-alerts')->assertSuccessful();

        $this->assertDatabaseHas('mailing_deliverability_alerts', [
            'campaign_id' => $campaign->id,
            'alert_type'  => DeliverabilityAlertType::SPAM_COMPLAINT_HIGH->value,
        ]);

        Notification::assertSentTo($admin, DeliverabilityAlertNotification::class);
    }

    // -------------------------------------------------------------------------
    // Both alerts for same campaign
    // -------------------------------------------------------------------------

    public function test_both_alerts_fire_for_same_campaign(): void
    {
        Notification::fake();
        $this->adminUser();
        $campaign = $this->completedCampaign();

        // 10 sent, 1 hard bounce (10%) + 1 spam complaint (10%)
        $messages = CampaignMessage::factory()->sent()->count(10)->create(['campaign_id' => $campaign->id]);

        MessageEvent::create(['message_id' => $messages[0]->id, 'event_type' => MessageEventType::BOUNCED_HARD->value, 'occurred_at' => now()]);
        MessageEvent::create(['message_id' => $messages[1]->id, 'event_type' => MessageEventType::COMPLAINED->value,  'occurred_at' => now()]);

        $this->artisan('mailing:check-deliverability-alerts')->assertSuccessful();

        $this->assertDatabaseCount('mailing_deliverability_alerts', 2);
    }

    // -------------------------------------------------------------------------
    // Idempotency — no duplicate alerts
    // -------------------------------------------------------------------------

    public function test_alert_not_duplicated_on_second_run(): void
    {
        Notification::fake();
        $this->adminUser();
        $campaign = $this->completedCampaign();

        $this->seedMessages($campaign, 10, 1, MessageEventType::BOUNCED_HARD->value);

        $this->artisan('mailing:check-deliverability-alerts')->assertSuccessful();
        $this->artisan('mailing:check-deliverability-alerts')->assertSuccessful();

        $this->assertDatabaseCount('mailing_deliverability_alerts', 1);

        // Notification sent only once (first run)
        Notification::assertSentTimes(DeliverabilityAlertNotification::class, 1);
    }

    // -------------------------------------------------------------------------
    // Campaign scope
    // -------------------------------------------------------------------------

    public function test_non_completed_campaigns_not_evaluated(): void
    {
        Notification::fake();

        $sending = Campaign::factory()->create([
            'status'      => CampaignStatus::SENDING,
            'finished_at' => now()->subHour(),
            'template_id' => EmailTemplate::factory()->create()->id,
        ]);

        $this->seedMessages($sending, 10, 1, MessageEventType::BOUNCED_HARD->value);

        $this->artisan('mailing:check-deliverability-alerts')->assertSuccessful();

        $this->assertDatabaseCount('mailing_deliverability_alerts', 0);
    }

    public function test_old_completed_campaigns_not_evaluated(): void
    {
        Notification::fake();

        $old = $this->completedCampaign(daysAgo: 10); // older than alert_check_days=7
        $this->seedMessages($old, 10, 1, MessageEventType::BOUNCED_HARD->value);

        config(['mailing.alert_check_days' => 7]);

        $this->artisan('mailing:check-deliverability-alerts')->assertSuccessful();

        $this->assertDatabaseCount('mailing_deliverability_alerts', 0);
    }

    // -------------------------------------------------------------------------
    // --dry-run
    // -------------------------------------------------------------------------

    public function test_dry_run_does_not_create_alerts_or_notify(): void
    {
        Notification::fake();
        $this->adminUser();
        $campaign = $this->completedCampaign();

        $this->seedMessages($campaign, 10, 1, MessageEventType::BOUNCED_HARD->value);

        $this->artisan('mailing:check-deliverability-alerts --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('mailing_deliverability_alerts', 0);
        Notification::assertNothingSent();
    }
}
