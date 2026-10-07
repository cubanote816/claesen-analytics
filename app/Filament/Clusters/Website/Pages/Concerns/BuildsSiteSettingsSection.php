<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Pages\Concerns;

use App\Filament\Clusters\Website\Concerns\ShowsSitePublicationState;
use App\Filament\Clusters\Website\WebsiteSettingSections;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Lang;
use Modules\Core\Models\Site;
use Modules\Website\Models\SiteSetting;

/**
 * El formulario de **una** sección de ajustes.
 *
 * Vive en un trait y no en una clase base abstracta por una razón práctica: Filament
 * auto-descubre las `Page` de este directorio, y una base abstracta ahí dentro es un riesgo que
 * no hace falta correr. Las seis páginas concretas usan este trait y sólo declaran su sección.
 *
 * **La lógica de los campos se movió, no se rehizo**: el domicilio sigue siendo cuatro campos,
 * `opening_hours` sigue siendo un `Repeater` de un día por fila con el `Toggle` derivado de
 * `hours === null`, y el correo sigue teniendo su regla. Lo único que cambió es de dónde salen
 * las claves: `sectionKeys()` en lugar del whitelist entero. Eso es lo que hace que guardar
 * Contacto no toque los horarios — y es la mitad de «cada página se publica por su cuenta».
 *
 * F3/CLA-469: sigue siendo un formulario de slots fijos, no un CRUD, y sigue atado al sitio del
 * panel (`Site::forPanelOrFail()`), que es lo que permite que el mismo clúster sirva a Claesen y
 * a Bertels.
 */
trait BuildsSiteSettingsSection
{
    use InteractsWithForms;
    use ShowsSitePublicationState;

    /**
     * Las claves de dirección que define el contrato del sitio. Cualquier otra se descarta al
     * guardar: una clave ajena viajaría intacta al snapshot del sitio.
     *
     * @var list<string>
     */
    private const ADDRESS_KEYS = ['street', 'postal_code', 'city', 'country_code'];

    /**
     * Las cuatro claves de día que acepta el contrato del snapshot — no siete. El primer
     * seeder fue rechazado por el validador del propio sitio con exactamente esta lista:
     * `opening_hours.day_key must be one of sunday, monday, tuesdayToFriday, saturday`.
     *
     * @var list<string>
     */
    private const OPENING_HOURS_DAY_KEYS = ['sunday', 'monday', 'tuesdayToFriday', 'saturday'];

    /**
     * `$cluster` y `$view` **no** se declaran acá a propósito: `Filament\Pages\Page` los define,
     * y una propiedad de trait que difiere de la del padre es un error fatal de composición
     * («define the same property … however, the definition differs»). Las declara cada página
     * concreta, donde sobrescribir el valor por defecto sí está permitido.
     */
    public ?array $data = [];

    /** La sección que edita esta página. La declara cada página concreta. */
    abstract public static function section(): string;

    /**
     * Las claves de esta sección, con su tipo, en el orden del whitelist.
     *
     * @return array<string, string>
     */
    protected static function sectionKeys(): array
    {
        $allowed = SiteSetting::allowedKeys();

        return collect(WebsiteSettingSections::keys(static::section()))
            ->mapWithKeys(fn (string $key): array => [$key => $allowed[$key] ?? SiteSetting::TYPE_TEXT])
            ->all();
    }

    public static function getNavigationLabel(): string
    {
        return __('website.settings_sections.'.static::section().'.label');
    }

    public static function canAccess(): bool
    {
        return parent::canAccess() && Site::forPanel() !== null;
    }

    public function getTitle(): string
    {
        return static::getNavigationLabel();
    }

    public function mount(): void
    {
        $siteId = Site::forPanelOrFail()->id;
        $settings = SiteSetting::query()
            ->where('site_id', $siteId)
            ->get()
            ->keyBy('key');

        $state = [];

        foreach (self::sectionKeys() as $key => $type) {
            $setting = $settings->get($key);
            $value = $setting?->value;

            $state[$key] = match ($type) {
                SiteSetting::TYPE_TRANSLATABLE => is_array($value) ? $value : [],
                SiteSetting::TYPE_JSON => match (true) {
                    // Las claves con forma propia se editan por partes, no como JSON.
                    $key === 'social_links',
                    $key === 'address_structured' => is_array($value) ? $value : [],
                    // `opening_hours` llega al formulario como filas de día, no como el array
                    // del contrato: el `Toggle` que dice «cerrado» se deriva de `hours === null`
                    // en vez de guardarse al lado, así que hay una sola fuente de verdad.
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

        foreach (self::sectionKeys() as $key => $type) {
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

            // 'social_links' conserva su Repeater propio (forma CLA-469, congelada para el panel
            // de Claesen); el resto de las claves JSON tienen editor de JSON crudo validado en el
            // servidor. Ramas explícitas: un brazo `TYPE_JSON && $key === 'social_links'` dentro
            // de un `match` evalúa a bool y nunca puede coincidir con $type.
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
                                    // La lista de días del contrato es un conjunto: dos filas del
                                    // mismo día llegarían al snapshot como dos domingos.
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

            // `->email()` a secas no alcanza, y no es hipotético: el valor que de verdad se
            // guardó fue `info@electrobertels.be5`, que pasa la validación RFC porque un dígito
            // es legal en un TLD. La regla extra exige que el TLD sea sólo letras. NO caza un
            // error como `info@electrobertels.bee` —esa es una dirección válida— así que es una
            // guarda contra la clase de error que ocurrió, no una promesa.
            $fields[] = $key === 'email'
                ? $input->email()->regex('/^[^@\s]+@[^@\s]+\.[a-zA-Z]{2,}$/')
                : $input;
        }

        return $schema->schema($fields)->statePath('data');
    }

    /**
     * Nota opcional por clave (por ejemplo la del domicilio que exige la decisión 2A). Se
     * devuelve sólo si la traducción existe — si no, `__()` filtraría la clave cruda a la UI.
     */
    private static function hintFor(string $key): ?string
    {
        $translationKey = "website.site_settings.hints.{$key}";

        return Lang::has($translationKey)
            ? __($translationKey)
            : null;
    }

    /**
     * Las etiquetas de día del `Select`, con las claves del contrato. Las etiquetas son chrome
     * (se traducen), las claves son datos y nunca se tocan.
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
     * Forma del contrato -> forma del formulario. `hours === null` ES el «cerrado» del contrato,
     * así que el `Toggle` se deriva de eso en vez de guardarse al lado: dos representaciones del
     * mismo hecho podrían contradecirse, una no.
     *
     * Los días que el contrato no define se descartan también al entrar, así una lista heredada
     * de siete días se ve como los cuatro editables en lugar de desaparecer al guardar.
     *
     * El orden se **conserva**, no se reimpone: el array guardado es el que tiene el snapshot del
     * sitio, y reordenarlo acá reescribiría el payload contra el que el sync compara.
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
                    // Un día cerrado llega igual con un período en blanco, para que abrir el
                    // `Toggle` deje una fila lista en vez de un hueco detrás de un botón.
                    // `defaultItems` no puede hacerlo: cuando el día se abre, el repeater ya
                    // tiene estado. Un día cerrado nunca lo guarda: el mapper escribe
                    // `hours: null` siempre que el `Toggle` dice cerrado.
                    'hours' => $ranges === [] ? [['open' => null, 'close' => null]] : $ranges,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Forma del formulario -> forma del contrato, la inversa de openingHoursForForm().
     *
     * El `Select` ya hace inalcanzable un día desconocido desde la UI y la validación corta antes:
     * ésa es la trampa que una vez produjo un informe de bug falso —un test metía un `day_key`
     * inválido, el guardado abortaba, y la columna vacía parecía un defecto del mapper—. Esto es
     * la línea de defensa detrás de las dos, para que un día fuera del contrato no llegue al
     * snapshot ni aunque llegue igual.
     *
     * Un día abierto sin ningún rango usable colapsa a `hours: null` (cerrado): el contrato tiene
     * `null` o una lista no vacía, nada en el medio. El formulario exige al menos un rango para un
     * día abierto, así que esto sólo atrapa entrada que nunca vino de él.
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

        // Sólo las claves de esta sección: guardar Contacto no escribe los horarios, y por lo
        // tanto no los marca como cambiados para nadie.
        foreach (self::sectionKeys() as $key => $type) {
            $value = $state[$key] ?? null;

            if ($type === SiteSetting::TYPE_TRANSLATABLE) {
                $value = array_filter($value ?? [], fn ($v) => filled($v));
            }

            if ($key === 'address_structured') {
                // Sólo las claves que define el contrato del consumidor. Una clave ajena viajaría
                // intacta al snapshot del sitio, y el orden es el de la base de todas formas: la
                // columna JSON de MySQL reordena las claves por longitud, así que escribirlas en
                // un orden «canónico» acá no compra nada.
                $value = collect(self::ADDRESS_KEYS)
                    ->mapWithKeys(fn (string $part): array => [
                        $part => filled($value[$part] ?? null) ? (string) $value[$part] : null,
                    ])
                    ->reject(fn (?string $part): bool => $part === null)
                    ->all();
            }

            if ($key === 'opening_hours') {
                // El `Toggle` decide, no los rangos que quedaron en el estado: el contrato
                // expresa «cerrado» como `hours: null`, y lo que la lista de días no define se
                // descarta en vez de viajar al snapshot.
                $value = self::openingHoursForStorage($value);
            }

            // Las claves con editor de JSON crudo llegan como string — se decodifican antes de
            // persistir para que la columna `value` conserve su contrato de cast a array (el
            // `->rule('json')` del campo ya rechazó la entrada inválida).
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
