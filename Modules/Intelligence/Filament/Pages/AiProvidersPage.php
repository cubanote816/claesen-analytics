<?php

declare(strict_types=1);

namespace Modules\Intelligence\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Intelligence\Exceptions\TextGenerationException;
use Modules\Intelligence\Models\AiTaskSetting;
use Modules\Intelligence\TextGeneration\AiTaskSettingsService;
use Modules\Intelligence\TextGeneration\TextGenerationManager;

/**
 * CLA-714: choose the AI provider and model per task without touching code.
 * Only super_admin and admin (management). API keys stay in .env: this page
 * only says whether each provider has one.
 */
class AiProvidersPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $slug = 'ai-providers';

    protected static ?int $navigationSort = 91;

    protected string $view = 'intelligence::filament.pages.ai-providers';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.intelligence_hub');
    }

    public static function getNavigationLabel(): string
    {
        return __('intelligence::ai_providers.navigation_label');
    }

    public function getTitle(): string
    {
        return __('intelligence::ai_providers.title');
    }

    public function mount(): void
    {
        $tasks = [];

        foreach (AiTaskSettingsService::tasks() as $task) {
            $setting = AiTaskSetting::query()->where('task', $task)->first();
            $tasks[$task] = ['provider' => $setting?->provider, 'model' => $setting?->model];
        }

        $this->form->fill(['tasks' => $tasks]);
    }

    public function form(Schema $form): Schema
    {
        $components = [
            Section::make(__('intelligence::ai_providers.sections.keys'))
                ->description(__('intelligence::ai_providers.sections.keys_desc'))
                ->schema([Text::make(fn (): string => $this->keysSummary())]),
        ];

        foreach (AiTaskSettingsService::tasks() as $task) {
            $components[] = Section::make(__("intelligence::ai_providers.tasks.{$task}"))
                ->description(fn (): string => $this->effectiveDescription($task))
                ->statePath("tasks.{$task}")
                ->schema([
                    Select::make('provider')
                        ->label(__('intelligence::ai_providers.fields.provider'))
                        ->placeholder(__('intelligence::ai_providers.fields.use_configuration'))
                        ->options(AiTaskSettingsService::providerOptions())
                        ->rule(Rule::in(array_keys(AiTaskSettingsService::providerOptions())))
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('model', null)),
                    Select::make('model')
                        ->label(__('intelligence::ai_providers.fields.model'))
                        ->options(fn (Get $get): array => $this->modelOptions($get('provider')))
                        ->required(fn (Get $get): bool => filled($get('provider')))
                        // Without a provider the model is ignored ("Use the configuration"), so it is not validated.
                        ->disabled(fn (Get $get): bool => blank($get('provider')))
                        ->rules(fn (Get $get): array => filled($get('provider'))
                            ? [Rule::in(AiTaskSettingsService::models($get('provider')))]
                            : []),
                ])
                ->columns(2)
                ->footerActions([
                    Action::make("test_{$task}")
                        ->label(__('intelligence::ai_providers.actions.test'))
                        ->icon('heroicon-m-bolt')
                        ->color('gray')
                        ->action(fn () => $this->testConnection($task)),
                ]);
        }

        return $form->schema($components)->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('intelligence::ai_providers.actions.save'))
                ->submit('save')
                ->color('primary')
                ->icon('heroicon-m-check'),
        ];
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $service = app(AiTaskSettingsService::class);

        try {
            DB::transaction(function () use ($state, $service): void {
                foreach (AiTaskSettingsService::tasks() as $task) {
                    $choice = $state['tasks'][$task] ?? [];

                    if (blank($choice['provider'] ?? null)) {
                        $service->clear($task);
                    } else {
                        $service->save($task, (string) $choice['provider'], (string) ($choice['model'] ?? ''), auth()->id());
                    }
                }
            });
        } catch (TextGenerationException $e) {
            Notification::make()
                ->title(__('intelligence::ai_providers.notifications.save_failed_title'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('intelligence::ai_providers.notifications.saved_title'))
            ->success()
            ->send();
    }

    /** Sends a minimal prompt with the choice on screen (saved or not); never saves. */
    public function testConnection(string $task): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(in_array($task, AiTaskSettingsService::tasks(), true), 404);

        $manager = app(TextGenerationManager::class);
        $choice = $this->data['tasks'][$task] ?? [];

        try {
            if (blank($choice['provider'] ?? null)) {
                // "Use the configuration": test what clearing the screen setting switches to.
                $resolved = $manager->resolve($task, skipSource: 'screen');
                $provider = $resolved['provider'];
                $model = $resolved['model'];
            } else {
                $provider = (string) $choice['provider'];
                $model = (string) ($choice['model'] ?? '');
                app(AiTaskSettingsService::class)->assertAllowed($task, $provider, $model);
            }

            $result = $manager->test($provider, $model);
        } catch (TextGenerationException $e) {
            Notification::make()
                ->title(__('intelligence::ai_providers.notifications.test_failed_title'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('intelligence::ai_providers.notifications.test_ok_title'))
            ->body(__('intelligence::ai_providers.notifications.test_ok_body', [
                'provider' => $result->provider,
                'model' => $result->model,
                'ms' => $result->durationMs,
            ]))
            ->success()
            ->send();
    }

    /**
     * Models for the chosen provider. With no provider ("Use the configuration")
     * the field is disabled and its value ignored on save; every allowlisted model
     * is accepted then, so a leftover value does not block clearing the override.
     *
     * @return array<string, string>
     */
    private function modelOptions(?string $provider): array
    {
        $models = filled($provider)
            ? AiTaskSettingsService::models($provider)
            : collect(array_keys(AiTaskSettingsService::providerOptions()))
                ->flatMap(fn (string $p): array => AiTaskSettingsService::models($p))
                ->unique()->values()->all();

        return array_combine($models, $models);
    }

    private function effectiveDescription(string $task): string
    {
        try {
            $resolved = app(TextGenerationManager::class)->resolve($task);
        } catch (TextGenerationException $e) {
            return __('intelligence::ai_providers.effective_invalid', ['reason' => $e->getMessage()]);
        }

        return __('intelligence::ai_providers.effective', [
            'provider' => config("intelligence.ai.providers.{$resolved['provider']}.label", $resolved['provider']),
            'model' => $resolved['model'],
            'source' => __("intelligence::ai_providers.sources.{$resolved['source']}"),
        ]);
    }

    private function keysSummary(): string
    {
        $configured = [
            // The path has a default, so check that the credential file really exists.
            'gemini' => is_string($path = config('services.gemini.service_account_path')) && $path !== '' && is_file($path),
            'anthropic' => filled(config('services.anthropic.key')),
        ];

        return collect(AiTaskSettingsService::providerOptions())
            ->map(fn (string $label, string $provider): string => $label.': '.__(
                ($configured[$provider] ?? false) ? 'intelligence::ai_providers.keys.configured' : 'intelligence::ai_providers.keys.missing'
            ))
            ->implode(' · ');
    }
}
