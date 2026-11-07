<?php

namespace Modules\Mailing\Tests\Feature;

use App\Contracts\MarketingCampaignInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Tests\TestCase;

/**
 * CLA — Tracking links (open pixel + click redirect) must use the public
 * tracking domain (mailing.tracking_domain), not the internal APP_URL, so
 * recipients outside the local network can reach them.
 */
class TrackingDomainTest extends TestCase
{
    use RefreshDatabase;

    private string $html = '';

    protected function setUp(): void
    {
        parent::setUp();
        config(['mailing.send_delay_ms' => 0]);
        config([
            'mail.mailers.microsoft-graph.client_id'     => 'test-client-id',
            'mail.mailers.microsoft-graph.tenant_id'     => 'test-tenant-id',
            'mail.mailers.microsoft-graph.client_secret' => 'test-client-secret',
        ]);

        $mock = Mockery::mock(MarketingCampaignInterface::class);
        $mock->shouldReceive('sendCampaign')->andReturnUsing(
            function ($prospect, $emails, $subject, $html) {
                $this->html = $html;

                return true;
            }
        );
        $this->app->instance(MarketingCampaignInterface::class, $mock);
    }

    private function sendCampaign(): void
    {
        $region = Region::firstOrCreate(['name' => 'Test Region'], ['slug' => 'test-region']);
        $prospect = Prospect::create(['name' => 'Club-' . uniqid(), 'region_id' => $region->id]);
        ProspectLocation::create([
            'prospect_id'  => $prospect->id,
            'contact_type' => 'main',
            'email'        => 'a@example.com',
        ]);

        $template = EmailTemplate::create([
            'name'     => 'Inline Template',
            'subject'  => 'Hello {{ name }}',
            'body'     => '<p>Dear {{ name }}</p><a href="https://example.com/x">x</a>',
            'category' => TemplateCategory::TRANSACTIONAL,
        ]);

        $job = new ExecuteCampaignJob(prospectIds: [$prospect->id], templateId: $template->id);
        $job->handle(app(MarketingCampaignInterface::class), app(\Modules\Mailing\Services\SuppressionService::class));
    }

    public function test_open_pixel_and_click_use_configured_public_tracking_domain(): void
    {
        config(['app.url' => 'https://backoffice.claesen.local']);
        config(['mailing.tracking_domain' => 'https://www.claesen-verlichting.be']);

        $this->sendCampaign();

        $this->assertStringContainsString('https://www.claesen-verlichting.be/mailing/track/open/', $this->html);
        $this->assertStringContainsString('https://www.claesen-verlichting.be/mailing/track/click/', $this->html);
        // Tracking links must not leak the internal backoffice host.
        $this->assertStringNotContainsString('backoffice.claesen.local/mailing/track/', $this->html);
    }

    public function test_tracking_domain_with_trailing_slash_does_not_double_slash(): void
    {
        config(['mailing.tracking_domain' => 'https://www.claesen-verlichting.be/']);

        $this->sendCampaign();

        $this->assertStringContainsString('https://www.claesen-verlichting.be/mailing/track/open/', $this->html);
        $this->assertStringNotContainsString('.be//mailing', $this->html);
    }

    public function test_falls_back_to_app_url_when_tracking_domain_empty(): void
    {
        config(['mailing.tracking_domain' => null]);
        config(['app.url' => 'https://fallback.example.test']);

        $this->sendCampaign();

        $this->assertStringContainsString('https://fallback.example.test/mailing/track/open/', $this->html);
        $this->assertStringContainsString('https://fallback.example.test/mailing/track/click/', $this->html);
    }
}
