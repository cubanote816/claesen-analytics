<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website\WebsiteCluster;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Core\Models\Site;
use Modules\Website\Models\SiteSetting;

/**
 * F3/CLA-469 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * A settings FORM (fixed slots from config('website.site_settings.allowed_keys')),
 * not a CRUD resource — same "no page builder" boundary the ticket draws
 * explicitly. Hardcoded to Claesen's site (Site::forPanelOrFail()->id), same rationale
 * as ProjectResource::categoryOptions()/CreateAnnouncement — Bertels has no
 * panel yet (F3/F4).
 *
 * 'hours' (type 'translatable') gets one plain TextInput per locale here
 * rather than routing through HasAiTranslations/a single-field-plus-AI-
 * translation flow — this table's `value` column isn't itself a
 * HasTranslations attribute (it's a manual per-key JSON blob covering three
 * different value shapes), so wiring automatic translation for one of those
 * shapes is out of scope for a first version of this settings page.
 */
class SiteSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $slug = 'site-settings';

    protected string $view = 'website::filament.pages.site-settings';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('website.site_settings.label');
    }

    public static function canAccess(): bool
    {
        return parent::canAccess() && Site::forPanel() !== null;
    }

    public function getTitle(): string
    {
        return __('website.site_settings.label');
    }

    public function mount(): void
    {
        $siteId = Site::forPanelOrFail()->id;
        $settings = SiteSetting::query()
            ->where('site_id', $siteId)
            ->get()
            ->keyBy('key');

        $state = [];

        foreach (SiteSetting::allowedKeys() as $key => $type) {
            $setting = $settings->get($key);
            $value = $setting?->value;

            $state[$key] = match ($type) {
                SiteSetting::TYPE_TRANSLATABLE => is_array($value) ? $value : [],
                SiteSetting::TYPE_JSON => is_array($value) ? $value : [],
                default => is_string($value) ? $value : null,
            };
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $fields = [];

        foreach (SiteSetting::allowedKeys() as $key => $type) {
            $fields[] = match ($type) {
                SiteSetting::TYPE_TRANSLATABLE => Section::make(__("website.site_settings.fields.{$key}"))
                    ->schema([
                        TextInput::make("{$key}.nl")->label('NL'),
                        TextInput::make("{$key}.en")->label('EN'),
                        TextInput::make("{$key}.fr")->label('FR'),
                        TextInput::make("{$key}.de")->label('DE'),
                    ])
                    ->columns(2),
                SiteSetting::TYPE_JSON => Section::make(__("website.site_settings.fields.{$key}"))
                    ->schema([
                        Repeater::make($key)
                            ->label('')
                            ->schema([
                                TextInput::make('platform')->required(),
                                TextInput::make('url')->url()->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel(__('website.site_settings.fields.social_links')),
                    ]),
                default => TextInput::make($key)->label(__("website.site_settings.fields.{$key}")),
            };
        }

        return $schema->schema($fields)->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('website.site_settings.save'))
                ->submit('save')
                ->color('primary'),
        ];
    }

    public function save(): void
    {
        $siteId = Site::forPanelOrFail()->id;
        $state = $this->form->getState();

        foreach (SiteSetting::allowedKeys() as $key => $type) {
            $value = $state[$key] ?? null;

            if ($type === SiteSetting::TYPE_TRANSLATABLE) {
                $value = array_filter($value ?? [], fn ($v) => filled($v));
            }

            SiteSetting::query()->updateOrCreate(
                ['site_id' => $siteId, 'key' => $key],
                ['value' => $value, 'type' => $type],
            );
        }

        Notification::make()
            ->title(__('website.site_settings.saved'))
            ->success()
            ->send();
    }
}
