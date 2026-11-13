<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Seeders\KnxDemoSeeder;
use Modules\Knx\Models\KnxDocument;
use Modules\Knx\Models\KnxPlanAiAuthorization;
use Modules\Knx\Models\KnxProject;
use Tests\TestCase;

/**
 * Per-document, per-provider authorization to send a plan to an AI provider.
 *
 * A document can never be sent unless a person authorized THAT document for THAT
 * provider: no row means forbidden, a grant covers one provider only, and a revocation
 * takes effect immediately.
 */
final class KnxPlanAiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Organization::factory()->create(['slug' => 'electro-bertels']);

        $this->seed(KnxDemoSeeder::class);

        $this->actingAs(
            User::query()->where('email', 'lien.smet@electrobertels.be')->sole(),
            'sanctum',
        );
    }

    private function document(string $name = 'UV_C1618_Gelijkvloers_Wayfinding.pdf'): KnxDocument
    {
        $project = KnxProject::query()->where('code', 'C1618')->sole();

        return KnxDocument::query()->where('project_id', $project->getKey())->where('name', $name)->sole();
    }

    private function url(KnxDocument $document, string $provider = ''): string
    {
        $base = "/api/v1/knx/projects/C1618/plans/{$document->getKey()}/ai-authorizations";

        return $provider === '' ? $base : "{$base}/{$provider}";
    }

    public function test_by_default_no_provider_is_authorized_for_a_document(): void
    {
        $document = $this->document();

        $response = $this->getJson($this->url($document))->assertOk();

        $this->assertSame((string) $document->getKey(), $response->json('documentId'));
        $this->assertSame(['synthetic', 'openai', 'chatgpt'], array_column($response->json('providers'), 'provider'));
        foreach ($response->json('providers') as $entry) {
            $this->assertSame(['provider', 'external', 'billing', 'available', 'authorized', 'grantedAt', 'grantedBy'], array_keys($entry));
            $this->assertFalse($entry['authorized']);
            $this->assertNull($entry['grantedAt']);
            $this->assertNull($entry['grantedBy']);
        }
        // The OpenAI adapter ships switched off; the synthetic provider never leaves the server.
        $byProvider = array_column($response->json('providers'), null, 'provider');
        $this->assertTrue($byProvider['openai']['external']);
        $this->assertFalse($byProvider['openai']['available']);
        $this->assertFalse($byProvider['synthetic']['external']);
        $this->assertTrue($byProvider['synthetic']['available']);
        $this->assertSame(['simulation', 'api', 'subscription'], array_column($byProvider, 'billing'));
        // The ChatGPT plan is per employee: nobody has connected one here.
        $this->assertFalse($byProvider['chatgpt']['available']);
    }

    public function test_a_grant_covers_that_document_and_that_provider_only(): void
    {
        $document = $this->document();

        $this->putJson($this->url($document, 'openai'))
            ->assertOk()
            ->assertJsonPath('provider', 'openai')
            ->assertJsonPath('authorized', true)
            ->assertJsonPath('grantedBy', 'Lien Smet');

        $byProvider = array_column($this->getJson($this->url($document))->json('providers'), null, 'provider');
        $this->assertTrue($byProvider['openai']['authorized']);
        $this->assertNotNull($byProvider['openai']['grantedAt']);
        $this->assertFalse($byProvider['synthetic']['authorized']);

        // Another document of the same project stays forbidden.
        $other = KnxDocument::query()
            ->where('project_id', $document->project_id)
            ->whereKeyNot($document->getKey())
            ->firstOrFail();
        foreach ($this->getJson($this->url($other))->json('providers') as $entry) {
            $this->assertFalse($entry['authorized']);
        }
    }

    public function test_granting_twice_keeps_one_authorization(): void
    {
        $document = $this->document();

        $this->putJson($this->url($document, 'synthetic'))->assertOk();
        $this->putJson($this->url($document, 'synthetic'))->assertOk();

        $this->assertSame(1, KnxPlanAiAuthorization::query()->where('document_id', $document->getKey())->count());
    }

    public function test_a_revocation_forbids_the_document_again(): void
    {
        $document = $this->document();
        $this->putJson($this->url($document, 'openai'))->assertOk();

        $this->deleteJson($this->url($document, 'openai'))
            ->assertOk()
            ->assertJsonPath('authorized', false)
            ->assertJsonPath('grantedAt', null);

        $this->assertSame(0, KnxPlanAiAuthorization::query()->where('document_id', $document->getKey())->count());
        // Revoking what was never granted is harmless.
        $this->deleteJson($this->url($document, 'synthetic'))->assertOk()->assertJsonPath('authorized', false);
    }

    public function test_an_unknown_provider_is_a_404_and_stores_nothing(): void
    {
        $document = $this->document();

        $this->putJson($this->url($document, 'gemini'))->assertNotFound();
        $this->deleteJson($this->url($document, 'gemini'))->assertNotFound();

        $this->assertSame(0, KnxPlanAiAuthorization::query()->count());
    }

    public function test_a_document_of_another_project_is_a_404(): void
    {
        $foreign = KnxDocument::query()
            ->whereHas('project', fn ($q) => $q->where('code', '!=', 'C1618'))
            ->firstOrFail();

        $this->getJson("/api/v1/knx/projects/C1618/plans/{$foreign->getKey()}/ai-authorizations")->assertNotFound();
        $this->putJson("/api/v1/knx/projects/C1618/plans/{$foreign->getKey()}/ai-authorizations/openai")->assertNotFound();

        $this->assertSame(0, KnxPlanAiAuthorization::query()->count());
    }

    public function test_a_guest_or_a_field_user_cannot_read_or_grant(): void
    {
        $document = $this->document();
        $this->app['auth']->forgetGuards();

        $this->getJson($this->url($document))->assertUnauthorized();
        $this->putJson($this->url($document, 'openai'))->assertUnauthorized();

        // A field (Veld) user is not an office user either.
        $this->actingAs(User::query()->where('email', 'jan.van.dyck@electrobertels.be')->sole(), 'sanctum');
        $this->putJson($this->url($document, 'openai'))->assertUnauthorized();

        $this->assertSame(0, KnxPlanAiAuthorization::query()->count());
    }
}
