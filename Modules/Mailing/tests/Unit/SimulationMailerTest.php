<?php

namespace Modules\Mailing\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Modules\Mailing\Exceptions\MailConfigurationException;
use Modules\Mailing\Services\SimulationMailer;
use Modules\Prospects\Models\Prospect;
use Tests\TestCase;

/**
 * CLA-532: the explicit simulation transport must never perform HTTP and must
 * refuse to run in production.
 */
class SimulationMailerTest extends TestCase
{
    private function prospect(): Prospect
    {
        // Unsaved model — this test never touches the database.
        return (new Prospect)->forceFill(['id' => 1, 'name' => 'Test', 'language' => 'nl']);
    }

    public function test_it_returns_true_and_sends_no_http_outside_production(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $this->app['env'] = 'testing';

        $result = (new SimulationMailer)->sendCampaign(
            $this->prospect(),
            ['someone@customer.example'],
            'Subject',
            '<p>Body</p>',
            'https://claesen-verlichting.be/afmelden/?p=1',
        );

        $this->assertTrue($result);
        Http::assertNothingSent();
    }

    public function test_it_is_refused_in_production(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $originalEnv = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $caught = null;
            try {
                (new SimulationMailer)->sendCampaign(
                    $this->prospect(),
                    ['someone@customer.example'],
                    'Subject',
                    '<p>Body</p>',
                    'https://claesen-verlichting.be/afmelden/?p=1',
                );
            } catch (MailConfigurationException $e) {
                $caught = $e;
            }

            $this->assertInstanceOf(MailConfigurationException::class, $caught);
            $this->assertStringContainsString('not allowed in production', $caught->getMessage());
            Http::assertNothingSent();
        } finally {
            $this->app['env'] = $originalEnv;
        }
    }
}
