<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Website\Models\ConsultationEmailDelivery;
use Modules\Website\Models\ConsultationRequest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

/**
 * CLA-692: the test suite must never send real e-mail through Microsoft Graph.
 *
 * SendConsultationEmailJob sends with the named Mail::mailer('microsoft-graph'),
 * which ignores phpunit.xml's MAIL_MAILER=array, and QUEUE_CONNECTION=sync runs
 * it inside the test. With real MICROSOFT_GRAPH_* values in a developer's .env,
 * every test that created a consultation sent a real notice.
 *
 * This class puts Graph credentials into the process environment *before* the
 * application boots, the same way a real .env would, and checks that a
 * consultation created through the public API still makes no Graph request.
 *
 * Isolated processes, as in AlwaysToConfigTest (CLA-532): in the shared suite
 * process a .env that lists these keys (CI copies .env.example) re-clobbers
 * the pre-boot values, and the test would pass without proving anything.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class TestSuiteMailIsolationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{env: mixed, server: mixed, putenv: string|false}> */
    private array $previousEnv = [];

    /**
     * Process environment set before the application boots, like a real .env.
     *
     * @return array<string, string>
     */
    protected function environment(): array
    {
        return [
            'MICROSOFT_GRAPH_CLIENT_ID' => 'env-client-id',
            'MICROSOFT_GRAPH_TENANT_ID' => 'env-tenant-id',
            'MICROSOFT_GRAPH_CLIENT_SECRET' => 'env-client-secret',
        ];
    }

    protected function setUp(): void
    {
        foreach ($this->environment() as $name => $value) {
            $this->previousEnv[$name] = [
                'env' => $_ENV[$name] ?? null,
                'server' => $_SERVER[$name] ?? null,
                'putenv' => getenv($name),
            ];
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ($this->previousEnv as $name => $previous) {
            if ($previous['env'] === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $previous['env'];
            }

            if ($previous['server'] === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $previous['server'];
            }

            putenv($previous['putenv'] === false ? $name : "{$name}={$previous['putenv']}");
        }
    }

    public function test_a_consultation_created_through_the_public_api_never_reaches_microsoft_graph(): void
    {
        Http::fake();
        $email = 'isolation-'.uniqid().'@example.test';

        $this->postJson('/v1/website/consultations', [
            'name' => 'Mail Isolation',
            'email' => $email,
            'message' => 'The test suite must not send this.',
        ])->assertCreated();

        Http::assertNothingSent();

        $consultation = ConsultationRequest::query()->where('email', $email)->sole();
        $deliveries = ConsultationEmailDelivery::query()
            ->where('consultation_request_id', $consultation->id)
            ->get();

        $this->assertNotEmpty($deliveries);
        foreach ($deliveries as $delivery) {
            $this->assertSame(ConsultationEmailDelivery::STATUS_FAILED, $delivery->status);
            $this->assertStringContainsString('MailConfigurationException', (string) $delivery->last_error);
        }
    }
}
