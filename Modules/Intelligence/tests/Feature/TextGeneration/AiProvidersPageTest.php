<?php

declare(strict_types=1);

namespace Modules\Intelligence\Tests\Feature\TextGeneration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\Filament\Pages\AiProvidersPage;
use Modules\Intelligence\Models\AiTaskSetting;
use Modules\Intelligence\Services\GoogleServiceAccountAuthService;
use Modules\Intelligence\TextGeneration\AiTaskSettingsService;
use Modules\Intelligence\TextGeneration\AnthropicMessageClient;
use Modules\Intelligence\TextGeneration\ScreenTaskSettingsSource;
use Modules\Intelligence\TextGeneration\TextGenerationManager;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * CLA-714 (S8c, S8d): management switches the AI provider and model per task
 * from a screen, limited to the allowlist, without touching code or keys.
 */
final class AiProvidersPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'intelligence.ai.default' => ['provider' => 'gemini', 'model' => 'gemini-2.5-flash'],
            'intelligence.ai.tasks' => ['lead_summary' => ['provider' => null, 'model' => null]],
            'intelligence.ai.providers' => [
                'gemini' => ['label' => 'Google Gemini', 'models' => ['gemini-2.5-flash', 'gemini-2.5-pro']],
                'anthropic' => ['label' => 'Anthropic Claude', 'models' => ['claude-sonnet-5', 'claude-haiku-5-5']],
            ],
            'services.anthropic.key' => 'sk-ant-SECRET-KEY-VALUE',
            'services.gemini.url' => 'https://vertex.test/v1/projects/p/locations/l/publishers/google/models/gemini-2.5-flash:generateContent',
        ]);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function page(string $role = 'admin')
    {
        return Livewire::actingAs($this->userWithRole($role))->test(AiProvidersPage::class);
    }

    private function manager(): TextGenerationManager
    {
        return app(TextGenerationManager::class);
    }

    public function test_only_super_admin_and_admin_can_access(): void
    {
        foreach (['super_admin' => true, 'admin' => true, 'financial_manager' => false, 'viewer' => false, 'technician' => false] as $role => $allowed) {
            $this->actingAs($this->userWithRole($role));
            $this->assertSame($allowed, AiProvidersPage::canAccess(), "role {$role}");
        }
    }

    public function test_other_roles_cannot_open_the_page(): void
    {
        $this->page('financial_manager')->assertForbidden();
        $this->page('technician')->assertForbidden();
    }

    public function test_save_is_refused_once_the_user_loses_access(): void
    {
        $user = $this->userWithRole('admin');
        $page = Livewire::actingAs($user)->test(AiProvidersPage::class)
            ->fillForm(['tasks' => ['lead_summary' => ['provider' => 'anthropic', 'model' => 'claude-sonnet-5']]]);

        $this->revokeAdmin($user);

        $page->call('save')->assertForbidden();
        $this->assertSame(0, AiTaskSetting::query()->count());
    }

    public function test_connection_test_is_refused_once_the_user_loses_access(): void
    {
        $user = $this->userWithRole('admin');
        $page = Livewire::actingAs($user)->test(AiProvidersPage::class)
            ->fillForm(['tasks' => ['lead_summary' => ['provider' => 'gemini', 'model' => 'gemini-2.5-flash']]]);

        $this->revokeAdmin($user);

        // Both outbound adapters are faked: nothing may reach Gemini or Anthropic.
        Http::preventStrayRequests();
        Http::fake();
        $anthropic = new class extends AnthropicMessageClient
        {
            public int $calls = 0;

            public function createMessage(string $apiKey, array $params): object
            {
                $this->calls++;

                return (object) [];
            }
        };
        $this->app->instance(AnthropicMessageClient::class, $anthropic);

        $page->call('testConnection', 'lead_summary')->assertForbidden();

        Http::assertNothingSent();
        $this->assertSame(0, $anthropic->calls);
    }

    public function test_a_missing_settings_table_falls_back_to_the_configuration(): void
    {
        $missingTable = new class extends AiTaskSetting
        {
            protected $table = 'intelligence_ai_task_settings_not_migrated';
        };

        $this->assertNull((new ScreenTaskSettingsSource($missingTable::class))->settingsFor('lead_summary'));
    }

    public function test_an_unexpected_settings_read_error_fails_closed(): void
    {
        // An existing table without a "task" column: unknown-column error, not a missing table.
        $brokenTable = new class extends AiTaskSetting
        {
            protected $table = 'users';
        };

        $this->expectException(TextGenerationException::class);
        $this->expectExceptionMessage('The AI screen settings could not be read.');

        (new ScreenTaskSettingsSource($brokenTable::class))->settingsFor('lead_summary');
    }

    public function test_a_saved_change_applies_to_the_next_resolution_without_any_cache(): void
    {
        $service = app(AiTaskSettingsService::class);
        $this->assertSame('default_config', $this->manager()->resolve('lead_summary')['source']);

        $service->save('lead_summary', 'anthropic', 'claude-sonnet-5', null);
        $this->assertSame('claude-sonnet-5', $this->manager()->resolve('lead_summary')['model']);

        $service->save('lead_summary', 'anthropic', 'claude-haiku-5-5', null);
        $this->assertSame('claude-haiku-5-5', $this->manager()->resolve('lead_summary')['model']);

        $service->clear('lead_summary');
        $this->assertSame('default_config', $this->manager()->resolve('lead_summary')['source']);
    }

    public function test_saving_the_same_choice_again_writes_and_audits_nothing(): void
    {
        $first = $this->userWithRole('admin');
        $service = app(AiTaskSettingsService::class);
        $service->save('lead_summary', 'anthropic', 'claude-sonnet-5', $first->id);
        $activityModel = config('activitylog.activity_model');
        $before = $activityModel::query()->count();

        $service->save('lead_summary', 'anthropic', 'claude-sonnet-5', $this->userWithRole('admin')->id);

        $this->assertSame($first->id, AiTaskSetting::query()->sole()->updated_by);
        $this->assertSame($before, $activityModel::query()->count());
    }

    public function test_a_save_that_does_not_land_is_reported_and_rolled_back(): void
    {
        AiTaskSetting::query()->create(['task' => 'lead_summary', 'provider' => 'gemini', 'model' => 'gemini-2.5-pro']);
        // Simulates a concurrent clear removing the row between our read and our update.
        AiTaskSetting::updating(fn (AiTaskSetting $s) => DB::table('intelligence_ai_task_settings')->where('id', $s->id)->delete());

        try {
            app(AiTaskSettingsService::class)->save('lead_summary', 'anthropic', 'claude-sonnet-5', null);
            $this->fail('Expected TextGenerationException');
        } catch (TextGenerationException $e) {
            $this->assertStringContainsString('changed by someone else', $e->getMessage());
        }

        $this->assertSame('gemini', AiTaskSetting::query()->sole()->provider);
    }

    private function revokeAdmin(User $user): void
    {
        $user->removeRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->refresh();
    }

    public function test_page_shows_the_effective_setting_and_never_the_api_key(): void
    {
        $this->page()
            ->assertOk()
            ->assertSee('gemini-2.5-flash')
            ->assertSee(__('intelligence::ai_providers.sources.default_config'))
            ->assertSee(__('intelligence::ai_providers.keys.configured'))
            ->assertDontSee('sk-ant-SECRET-KEY-VALUE');
    }

    public function test_saving_an_allowed_model_takes_effect_and_is_audited(): void
    {
        $this->assertSame('default_config', $this->manager()->resolve('lead_summary')['source']);

        $this->page()
            ->fillForm(['tasks' => ['lead_summary' => ['provider' => 'anthropic', 'model' => 'claude-haiku-5-5']]])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();

        $setting = AiTaskSetting::query()->where('task', 'lead_summary')->sole();
        $this->assertSame('anthropic', $setting->provider);
        $this->assertSame('claude-haiku-5-5', $setting->model);
        $this->assertNotNull($setting->updated_by);

        $this->assertSame(
            ['provider' => 'anthropic', 'model' => 'claude-haiku-5-5', 'source' => 'screen'],
            $this->manager()->resolve('lead_summary'),
        );

        $activityModel = config('activitylog.activity_model');
        $activity = $activityModel::query()->where('subject_type', $setting->getMorphClass())->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertSame($setting->updated_by, $activity->causer_id);
    }

    public function test_a_model_outside_the_allowlist_is_rejected_and_nothing_is_stored(): void
    {
        $this->page()
            ->fillForm(['tasks' => ['lead_summary' => ['provider' => 'gemini', 'model' => 'gemini-9-ultra']]])
            ->call('save')
            ->assertHasFormErrors(['tasks.lead_summary.model']);

        $this->assertSame(0, AiTaskSetting::query()->count());
    }

    public function test_the_fake_provider_cannot_be_chosen_on_screen(): void
    {
        $this->page()
            ->fillForm(['tasks' => ['lead_summary' => ['provider' => 'fake', 'model' => 'fake-model']]])
            ->call('save')
            ->assertHasFormErrors(['tasks.lead_summary.provider']);

        $this->assertSame(0, AiTaskSetting::query()->count());
    }

    public function test_clearing_the_provider_returns_to_the_configuration(): void
    {
        $page = $this->page()
            ->fillForm(['tasks' => ['lead_summary' => ['provider' => 'anthropic', 'model' => 'claude-sonnet-5']]])
            ->call('save');
        $this->assertSame('screen', $this->manager()->resolve('lead_summary')['source']);

        $page->fillForm(['tasks' => ['lead_summary' => ['provider' => null, 'model' => null]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, AiTaskSetting::query()->count());
        $this->assertSame(
            ['provider' => 'gemini', 'model' => 'gemini-2.5-flash', 'source' => 'default_config'],
            $this->manager()->resolve('lead_summary'),
        );
    }

    public function test_a_stored_model_later_removed_from_the_allowlist_fails_closed(): void
    {
        AiTaskSetting::query()->create(['task' => 'lead_summary', 'provider' => 'gemini', 'model' => 'gemini-2.5-pro']);
        config(['intelligence.ai.providers.gemini.models' => ['gemini-2.5-flash']]);

        $this->expectException(TextGenerationException::class);

        $this->manager()->resolve('lead_summary');
    }

    public function test_connection_test_calls_the_unsaved_choice_and_reports_success(): void
    {
        $this->mock(GoogleServiceAccountAuthService::class, fn ($mock) => $mock->shouldReceive('getAccessToken')->andReturn('fake-token'));
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'OK']]]]]])]);

        $this->page()
            ->fillForm(['tasks' => ['lead_summary' => ['provider' => 'gemini', 'model' => 'gemini-2.5-pro']]])
            ->call('testConnection', 'lead_summary')
            ->assertNotified(__('intelligence::ai_providers.notifications.test_ok_title'));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/models/gemini-2.5-pro:generateContent')
            && $request['contents'][0]['parts'][0]['text'] === 'Reply with the single word OK.'
            && $request['generationConfig']['maxOutputTokens'] === 16);
        $this->assertSame(0, AiTaskSetting::query()->count());
    }

    public function test_clearing_only_the_provider_removes_the_override(): void
    {
        AiTaskSetting::query()->create(['task' => 'lead_summary', 'provider' => 'anthropic', 'model' => 'claude-sonnet-5']);

        $this->page()
            ->fillForm(['tasks' => ['lead_summary' => ['provider' => null, 'model' => 'claude-sonnet-5']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, AiTaskSetting::query()->count());
        $this->assertSame('default_config', $this->manager()->resolve('lead_summary')['source']);
    }

    public function test_connection_test_with_use_the_configuration_tests_the_config_not_the_saved_override(): void
    {
        AiTaskSetting::query()->create(['task' => 'lead_summary', 'provider' => 'anthropic', 'model' => 'claude-sonnet-5']);
        $this->mock(GoogleServiceAccountAuthService::class, fn ($mock) => $mock->shouldReceive('getAccessToken')->andReturn('fake-token'));
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'OK']]]]]])]);

        $this->page()
            ->fillForm(['tasks' => ['lead_summary' => ['provider' => null, 'model' => null]]])
            ->call('testConnection', 'lead_summary')
            ->assertNotified(__('intelligence::ai_providers.notifications.test_ok_title'));

        // The default config is Gemini flash; the saved Anthropic override is not called.
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/models/gemini-2.5-flash:generateContent'));
        $this->assertSame('anthropic', AiTaskSetting::query()->sole()->provider);
    }

    public function test_gemini_key_status_requires_the_credential_file(): void
    {
        config(['services.gemini.service_account_path' => storage_path('app/does-not-exist.json')]);

        $this->page()->assertSee('Google Gemini: '.__('intelligence::ai_providers.keys.missing'));
    }

    public function test_connection_test_reports_a_failure_without_saving(): void
    {
        $this->mock(GoogleServiceAccountAuthService::class, fn ($mock) => $mock->shouldReceive('getAccessToken')->andReturn('fake-token'));
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        $this->page()
            ->fillForm(['tasks' => ['lead_summary' => ['provider' => 'gemini', 'model' => 'gemini-2.5-flash']]])
            ->call('testConnection', 'lead_summary')
            ->assertNotified(__('intelligence::ai_providers.notifications.test_failed_title'));

        $this->assertSame(0, AiTaskSetting::query()->count());
    }

    public function test_connection_test_refuses_a_model_outside_the_allowlist(): void
    {
        Http::preventStrayRequests();

        $this->page()
            ->fillForm(['tasks' => ['lead_summary' => ['provider' => 'gemini', 'model' => 'gemini-9-ultra']]])
            ->call('testConnection', 'lead_summary')
            ->assertNotified(__('intelligence::ai_providers.notifications.test_failed_title'));

        Http::assertNothingSent();
    }
}
