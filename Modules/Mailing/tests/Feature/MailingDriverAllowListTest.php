<?php

namespace Modules\Mailing\Tests\Feature;

use App\Contracts\MarketingCampaignInterface;
use Modules\Mailing\Exceptions\MailConfigurationException;
use Modules\Mailing\Services\MicrosoftGraphMailer;
use Modules\Mailing\Services\SaaSMailer;
use Modules\Mailing\Services\SimulationMailer;
use Tests\TestCase;

/**
 * CLA-532: the campaign transport is chosen fail-closed from
 * config('app.mailing_driver'). Only the three allow-listed values resolve; an
 * unset or unknown value raises MailConfigurationException — there is no
 * implicit fallback to a real transport. No database is used.
 */
class MailingDriverAllowListTest extends TestCase
{
    private function resolve(): MarketingCampaignInterface
    {
        // bind() (not singleton) — the closure re-reads config on every make().
        $this->app->forgetInstance(MarketingCampaignInterface::class);

        return $this->app->make(MarketingCampaignInterface::class);
    }

    public function test_simulation_resolves_to_simulation_mailer(): void
    {
        config(['app.mailing_driver' => 'simulation']);
        $this->assertInstanceOf(SimulationMailer::class, $this->resolve());
    }

    public function test_saas_resolves_to_saas_mailer(): void
    {
        config(['app.mailing_driver' => 'saas']);
        $this->assertInstanceOf(SaaSMailer::class, $this->resolve());
    }

    public function test_microsoft_graph_resolves_to_graph_mailer(): void
    {
        config(['app.mailing_driver' => 'microsoft-graph']);
        $this->assertInstanceOf(MicrosoftGraphMailer::class, $this->resolve());
    }

    public function test_unknown_value_raises_configuration_exception(): void
    {
        config(['app.mailing_driver' => 'grph']);

        $this->expectException(MailConfigurationException::class);
        $this->expectExceptionMessageMatches("/Unknown mailing driver: 'grph'/");

        $this->resolve();
    }

    public function test_unset_value_raises_configuration_exception(): void
    {
        config(['app.mailing_driver' => null]);

        $this->expectException(MailConfigurationException::class);
        $this->expectExceptionMessageMatches('/Unknown mailing driver/');

        $this->resolve();
    }
}
