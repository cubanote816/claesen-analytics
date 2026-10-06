<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website\WebsiteCluster;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Lang;
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

    /**
     * The address keys the site's contract defines. Anything else is dropped on save:
     * a stray key would travel into the site's snapshot untouched.
     *
     * @var list<string>
     */
    private const ADDRESS_KEYS = ['street', 'postal_code', 'city', 'country_code'];

    /**
     * The four day keys the site's snapshot contract accepts — not seven. The first
     * seeder was rejected by the site's own validator with exactly this list:
     * `opening_hours.day_key must be one of sunday, monday, tuesdayToFriday, saturday`.
     * `tuesdayToFriday` is the contract's shape and not a shortcut: the real hours share
     * one midday break across those four days.
     *
     * @var list<string>
     */
    private const OPENING_HOURS_DAY_KEYS = ['sunday', 'monday', 'tuesdayToFriday', 'saturday'];

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
                // 'social_links' keeps its structured Repeater; every other
                // JSON key is edited as raw JSON text (CLA-479 company facts
                // are heterogeneous shapes — scalar `founded_year`, object
                // `address_structured`, array `opening_hours`).
                SiteSetting::TYPE_JSON => match (true) {
                    // Keys with a shape of their own are edited as parts, not as JSON.
                    // `address_structured` is a fixed set of four fields, so there is
                    // nothing to gain from making someone type braces and quotes around
                    // them — and everything to lose: a stray keystroke inside a JSON
                    // blob is still valid JSON, which is how `"Balennn"` got saved.
                    $key === 'social_links',
                    $key === 'address_structured' => is_array($value) ? $value : [],
                    // `opening_hours` reaches the form as day rows, not as the contract's
                    // array: the toggle that says "closed" is derived from `hours === null`
                    // rather than stored, so there is one source of truth for it.
                    $key === 'opening_hours' => self::openingHoursForForm($value),
                    default => isset($value)
                        ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
                        : null,
                },
                default => is_string($value) ? $value : null,
            };
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $fields = [];

        foreach (SiteSetting::allowedKeys() as $key => $type) {
            if ($type === SiteSetting::TYPE_TRANSLATABLE) {
                $fields[] = Section::make(__("website.site_settings.fields.{$key}"))
                    ->schema([
                        TextInput::make("{$key}.nl")->label('NL'),
                        TextInput::make("{$key}.en")->label('EN'),
                        TextInput::make("{$key}.fr")->label('FR'),
                        TextInput::make("{$key}.de")->label('DE'),
                    ])
                    ->columns(2);

                continue;
            }

            // 'social_links' keeps its dedicated Repeater (CLA-469 shape,
            // frozen for the Claesen panel); all other JSON keys get a
            // raw-JSON editor validated server-side. Explicit branching —
            // a `TYPE_JSON && $key === 'social_links'` MATCH arm evaluates
            // to bool and can never match $type.
            if ($type === SiteSetting::TYPE_JSON && $key === 'social_links') {
                $fields[] = Section::make(__("website.site_settings.fields.{$key}"))
                    ->schema([
                        Repeater::make($key)
                            ->label('')
                            ->schema([
                                TextInput::make('platform')->required(),
                                TextInput::make('url')->url()->required(),
                            ])
                            ->columns(2)
                            ->addActionLabel(__('website.site_settings.fields.social_links')),
                    ]);

                continue;
            }

            if ($key === 'address_structured') {
                $fields[] = Section::make(__("website.site_settings.fields.{$key}"))
                    ->description(self::hintFor($key))
                    ->schema([
                        TextInput::make("{$key}.street")
                            ->label(__('website.site_settings.address.street')),
                        TextInput::make("{$key}.postal_code")
                            ->label(__('website.site_settings.address.postal_code')),
                        TextInput::make("{$key}.city")
                            ->label(__('website.site_settings.address.city')),
                        TextInput::make("{$key}.country_code")
                            ->label(__('website.site_settings.address.country_code'))
                            ->maxLength(2),
                    ])
                    ->columns(2);

                continue;
            }

            if ($key === 'opening_hours') {
                $fields[] = Section::make(__("website.site_settings.fields.{$key}"))
                    ->description(self::hintFor($key))
                    ->schema([
                        Repeater::make($key)
                            ->label('')
                            ->schema([
                                Select::make('day_key')
                                    ->label(__('website.site_settings.opening_hours.day'))
                                    ->options(self::openingHoursDayOptions())
                                    ->required()
                                    // The contract's day list is a set: two rows for the same
                                    // day would reach the site's snapshot as two Sundays.
                                    ->distinct(),
                                Toggle::make('closed')
                                    ->label(__('website.site_settings.opening_hours.closed'))
                                    ->live(),
                                Repeater::make('hours')
                                    ->label(__('website.site_settings.opening_hours.ranges'))
                                    ->schema([
                                        TimePicker::make('open')
                                            ->label(__('website.site_settings.opening_hours.opens'))
                                            ->format('H:i')
                                            ->seconds(false)
                                            ->required(),
                                        TimePicker::make('close')
                                            ->label(__('website.site_settings.opening_hours.closes'))
                                            ->format('H:i')
                                            ->seconds(false)
                                            ->required(),
                                    ])
                                    ->columns(2)
                                    ->minItems(1)
                                    ->defaultItems(1)
                                    ->addActionLabel(__('website.site_settings.opening_hours.add_range'))
                                    ->visible(fn (Get $get): bool => ! $get('closed')),
                            ])
                            ->columns(3)
                            ->addActionLabel(__('website.site_settings.opening_hours.add_day')),
                    ]);

                continue;
            }

            if ($type === SiteSetting::TYPE_JSON) {
                $fields[] = TextInput::make($key)
                    ->label(__("website.site_settings.fields.{$key}"))
                    ->hint(self::hintFor($key))
                    ->hintIcon('heroicon-m-information-circle')
                    ->rule('json');

                continue;
            }

            $input = TextInput::make($key)
                ->label(__("website.site_settings.fields.{$key}"))
                ->hint(self::hintFor($key))
                ->hintIcon('heroicon-m-information-circle');

            // `->email()` alone is not enough here, and this is not hypothetical: the
            // value that actually got saved was `info@electrobertels.be5`, which passes
            // RFC validation because a digit is legal in a TLD. The extra rule requires
            // the TLD to be letters only. It does NOT catch a typo like
            // `info@electrobertels.bee` — that one is simply a valid address — so this
            // is a guard against the class of mistake that happened, not a promise.
            $fields[] = $key === 'email'
                ? $input->email()->regex('/^[^@\s]+@[^@\s]+\.[a-zA-Z]{2,}$/')
                : $input;
        }

        return $schema->schema($fields)->statePath('data');
    }

    /**
     * Optional per-key hint text (e.g. the address/address_structured
     * consumer note required by approver decision 2A). Rendered only when
     * the translation key actually exists — __() would otherwise leak the
     * raw key string into the UI.
     */
    private static function hintFor(string $key): ?string
    {
        $translationKey = "website.site_settings.hints.{$key}";

        return Lang::has($translationKey)
            ? __($translationKey)
            : null;
    }

    /**
     * The day labels for the `Select`, keyed by the contract's own `day_key` values — the
     * labels are chrome (they get translated), the keys are data and never are.
     *
     * @return array<string, string>
     */
    private static function openingHoursDayOptions(): array
    {
        return collect(self::OPENING_HOURS_DAY_KEYS)
            ->mapWithKeys(fn (string $day): array => [
                $day => __("website.site_settings.opening_hours.days.{$day}"),
            ])
            ->all();
    }

    /**
     * Contract shape -> form shape. `hours === null` IS the contract's "closed", so the
     * toggle is derived from it rather than stored alongside it: two representations of
     * the same fact could disagree, one cannot.
     *
     * Days the contract does not define are dropped on the way in as well, so a legacy
     * seven-day list (the shape that got the first seeder rejected) shows up as the four
     * days that are actually editable instead of silently disappearing on save.
     *
     * Order is preserved, not re-imposed: the stored array is what the site's snapshot
     * holds, and reordering it here would rewrite the payload the sync compares against.
     *
     * @return list<array{day_key: string, closed: bool, hours: list<array{open: mixed, close: mixed}>}>
     */
    private static function openingHoursForForm(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->filter(fn (array $row): bool => self::isOpeningHoursDay(self::openingHoursDay($row)))
            ->map(function (array $row): array {
                $day = (string) self::openingHoursDay($row);
                $hours = $row['hours'] ?? null;

                $ranges = collect(is_array($hours) ? $hours : [])
                    ->filter(fn (mixed $range): bool => is_array($range))
                    ->map(fn (array $range): array => [
                        'open' => $range['open'] ?? null,
                        'close' => $range['close'] ?? null,
                    ])
                    ->values()
                    ->all();

                return [
                    'day_key' => $day,
                    'closed' => $hours === null,
                    // A closed day still arrives with one blank period, so flipping the toggle
                    // open leaves a row to fill instead of an empty area behind an "add"
                    // button. `defaultItems` cannot do this: by the time a day is opened the
                    // repeater already has state. A closed day never stores it — the mapper
                    // writes `hours: null` whenever the toggle says closed.
                    'hours' => $ranges === [] ? [['open' => null, 'close' => null]] : $ranges,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Form shape -> contract shape, the inverse of openingHoursForForm().
     *
     * The `Select` already makes an unknown day unreachable from the UI, and validation
     * stops the save before this runs — that is the trap that produced a false bug report
     * once: a test pushed a deliberately invalid `day_key`, the save aborted, and the empty
     * column looked like a mapper defect. This is the line of defence behind both, so a day
     * outside the contract can never reach the site's snapshot even if it arrives anyway.
     *
     * A day that is open with no usable range collapses to `hours: null` (closed): the
     * contract has `null` or a non-empty list, nothing in between. The form requires at
     * least one range for an open day, so this only catches input that never came from it.
     *
     * @return list<array{day_key: string, hours: null | list<array{open: string, close: string}>}>
     */
    private static function openingHoursForStorage(mixed $value): array
    {
        return collect(is_array($value) ? $value : [])
            ->filter(fn (mixed $row): bool => is_array($row))
            ->filter(fn (array $row): bool => self::isOpeningHoursDay(self::openingHoursDay($row)))
            ->map(function (array $row): array {
                $day = (string) self::openingHoursDay($row);
                $hours = null;

                if (! (bool) ($row['closed'] ?? false)) {
                    $ranges = collect(is_array($row['hours'] ?? null) ? $row['hours'] : [])
                        ->filter(fn (mixed $range): bool => is_array($range))
                        ->map(fn (array $range): array => [
                            'open' => $range['open'] ?? null,
                            'close' => $range['close'] ?? null,
                        ])
                        ->filter(fn (array $range): bool => filled($range['open']) && filled($range['close']))
                        ->map(fn (array $range): array => [
                            'open' => (string) $range['open'],
                            'close' => (string) $range['close'],
                        ])
                        ->values()
                        ->all();

                    $hours = $ranges === [] ? null : $ranges;
                }

                return ['day_key' => $day, 'hours' => $hours];
            })
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $row */
    private static function openingHoursDay(array $row): mixed
    {
        return $row['day_key'] ?? null;
    }

    private static function isOpeningHoursDay(mixed $day): bool
    {
        return is_string($day) && in_array($day, self::OPENING_HOURS_DAY_KEYS, true);
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

            if ($key === 'address_structured') {
                // Only the keys the consumer's contract defines. A stray key would be
                // carried into the site's snapshot untouched, and the order is the
                // database's anyway: MySQL's JSON column reorders object keys by length,
                // so writing them in a "canonical" order here buys nothing.
                $value = collect(self::ADDRESS_KEYS)
                    ->mapWithKeys(fn (string $part): array => [
                        $part => filled($value[$part] ?? null) ? (string) $value[$part] : null,
                    ])
                    ->reject(fn (?string $part): bool => $part === null)
                    ->all();
            }

            if ($key === 'opening_hours') {
                // The toggle decides, not the ranges left behind in the state: the contract
                // expresses "closed" as `hours: null`, and anything the day list does not
                // define is dropped rather than carried into the site's snapshot.
                $value = self::openingHoursForStorage($value);
            }

            // Raw-JSON editor keys arrive as a JSON string — decode before
            // persisting so the `value` column keeps its array-cast contract
            // (the field's ->rule('json') already rejected invalid input).
            if ($type === SiteSetting::TYPE_JSON
                && ! in_array($key, ['social_links', 'address_structured', 'opening_hours'], true)
            ) {
                $value = json_decode((string) $value, true);
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
