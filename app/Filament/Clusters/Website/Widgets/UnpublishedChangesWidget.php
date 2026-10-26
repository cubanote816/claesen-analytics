<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Widgets;

use App\Filament\Clusters\Website\WebsiteNavigationBadges;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Site;
use Modules\Website\Models\MediaSlot;
use Modules\Website\Models\PublicationState;
use Modules\Website\Models\SiteSetting;
use Modules\Website\Services\StaticSitePublicationService;

/**
 * Qué cambió desde la última publicación aceptada — WU4 de
 * `odd/tasks/publish-to-live-website.md`.
 *
 * La base de comparación es el `activity_log` que ya existía, y la ventana es «desde la última
 * publicación aceptada» (`PublicationState::last_accepted_at`). No hay hash de contenido: el
 * log ya guarda **quién**, **cuándo** y **qué atributos** se movieron, que es exactamente lo
 * que esta pregunta necesita.
 *
 * Se listan los **nombres de los campos** y no sus valores, a propósito: los valores viven
 * enteros en el recurso de log de actividad que el panel ya tiene, y volcarlos acá haría una
 * tabla ilegible con datos que pueden ser largos. La pregunta «¿qué cambió?» se responde con
 * «el domicilio, el correo y los horarios»; el detalle se mira donde ya se mira.
 *
 * Cobertura, declarada: sólo aparecen los modelos que registran actividad —
 * `SiteSetting`, `Project`, `MediaSlot` y `Announcement`. Cualquier otro cambio del panel no
 * se ve acá porque no deja rastro.
 *
 * La pregunta «¿hay algo sin publicar?» se responde con los mismos números que ve el menú
 * (`WebsiteNavigationBadges::hasUnpublishedChanges()`), no con esta fila de estado: una
 * publicación por sección la deja «accepted» sin haber cubierto el sitio, y el widget
 * terminaría listando como pendiente un cambio que su badge ya dio por publicado. Si ningún
 * badge cuenta, la lista se vacía y habla el estado vacío de abajo.
 */
class UnpublishedChangesWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Site::forPanel() !== null;
    }

    public function table(Table $table): Table
    {
        $siteId = Site::forPanelOrFail()->id;
        // Marca de agua por **id** y no por fecha: los `timestamp` de MySQL llegan al segundo, y
        // un cambio guardado en el mismo segundo que la publicación quedaba indistinguible de
        // ella. Sin marca de agua (publicaciones anteriores a WU4) se muestra todo lo registrado.
        $watermark = PublicationState::current($siteId)->last_published_activity_id;

        return $table
            ->heading(__('website.publication_history.changes_heading'))
            ->query(
                ActivityLogEntry::query()
                    // Eager load del sujeto: sin esto cada fila resolvería su `subject` con una
                    // consulta propia, y la columna de identificación lo necesita.
                    ->with('subject')
                    ->where('site_id', $siteId)
                    // Las publicaciones no son «cambios de contenido»: tienen su propia tabla
                    // justo arriba.
                    ->where('log_name', '!=', StaticSitePublicationService::PUBLICATION_LOG)
                    ->when($watermark !== null, fn ($query) => $query->where('id', '>', $watermark))
                    // Los mismos números que el menú: si ningún badge cuenta cambios, no hay
                    // nada pendiente que listar, publicado por sección o no.
                    ->when(! WebsiteNavigationBadges::hasUnpublishedChanges(),
                        fn ($query) => $query->whereRaw('1 = 0'))
                    ->orderByDesc('created_at')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('website.publication_history.fields.when'))
                    ->formatStateUsing(fn ($state): ?string => $state?->locale(app()->getLocale())->translatedFormat('d M Y H:i'))
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label(__('website.publication_history.fields.who'))
                    ->placeholder('—'),
                TextColumn::make('subject_type')
                    ->label(__('website.publication_history.fields.subject'))
                    ->badge()
                    ->state(function (ActivityLogEntry $record): string {
                        $label = self::subjectLabel($record->subject_type);
                        $detail = self::subjectDetail($record->subject);

                        return $detail === null ? $label : "{$label}: {$detail}";
                    }),
                TextColumn::make('attribute_changes')
                    ->label(__('website.publication_history.fields.fields_changed'))
                    // `->state()` con un escrito ya calculado, y no `formatStateUsing` sobre el
                    // array: con un estado de tipo array Filament **itera los elementos y junta
                    // los resultados formateados** de cada uno, y `attribute_changes` trae dos
                    // (`attributes` y `old`) — así salía cada campo duplicado («value, value»).
                    // Se lee el lado `attributes`, que es el que dice qué se movió, y se
                    // descartan los timestamps: cambian en cada guardado y no son «un cambio»
                    // para nadie.
                    ->state(function (ActivityLogEntry $record): string {
                        $changes = $record->attribute_changes ?? [];
                        $attributes = (array) (is_array($changes) || $changes instanceof \ArrayAccess
                            ? $changes['attributes'] ?? []
                            : []);

                        $fields = array_values(
                            array_diff(array_keys($attributes), ['updated_at', 'created_at'])
                        );

                        return $fields === [] ? '—' : implode(', ', $fields);
                    })
                    ->wrap(),
            ])
            ->emptyStateHeading(__('website.publication_history.empty_changes'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }

    /**
     * Qué **concreto** se tocó, cuando el nombre del modelo no alcanza.
     *
     * Un cambio de ajuste que se lee «Instelling» y un campo `value` no dice **cuál** ajuste, y
     * ésa es justamente la pregunta. Se usa el atributo con el que una persona lo reconoce —la
     * clave del ajuste, el nombre del slot— y se deja `null` cuando el modelo ya se explica solo
     * (un proyecto cambia su `title`, que se lee).
     */
    private static function subjectDetail(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof SiteSetting => $subject->key,
            $subject instanceof MediaSlot   => $subject->slot,
            default                         => null,
        };
    }

    /**
     * El nombre de la clase no le dice nada a quien aprueba. Se busca la etiqueta en el idioma
     * del panel y, si no existe, se devuelve el nombre corto tal cual — un valor sin etiqueta
     * se entiende, una clave de traducción cruda no.
     */
    private static function subjectLabel(?string $subjectType): string
    {
        if ($subjectType === null) {
            return '—';
        }

        $short = strtolower(class_basename($subjectType));
        $key = "website.publication_history.subjects.{$short}";
        $label = __($key);

        return $label === $key ? class_basename($subjectType) : $label;
    }
}
