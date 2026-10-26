<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Pages;

use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\WebsiteNavigationGroups;
use App\Filament\Clusters\Website\Widgets\UnpublishedChangesWidget;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Modules\Core\Models\ActivityLogEntry;
use Modules\Core\Models\Site;
use Modules\Website\Services\StaticSitePublicationService;

/**
 * Quién publicó y cuándo — WU4 de `odd/tasks/publish-to-live-website.md`.
 *
 * El historial vive en el `activity_log` que ya existía, y no en una tabla nueva, por una razón
 * medida: ese log ya guarda `site_id`, `causer_id`, fecha y el cambio, es **append-only** por
 * diseño (`ActivityLogEntry` prohíbe update y delete desde Eloquent), y ya tenía filas de este
 * sitio. Su precio, declarado: hereda `clean_after_days = 365`, o sea que el historial se poda
 * al año como todo el log. Si esa línea de tiempo no alcanza, hay que decidirlo aparte.
 *
 * Se muestran dos cosas y por eso hay dos tablas: **la solicitud y su resultado**, que son dos
 * filas y no una. La solicitud la escribe quien aprieta el botón (ahí hay usuario); el resultado
 * lo escribe el worker, donde no lo hay — son los dos extremos del mismo circuito, y separarlos
 * es lo que permite ver una publicación que se quedó sin desenlace.
 */
class PublicationHistoryPage extends Page implements HasTable
{
    use InteractsWithTable;

    public static function getNavigationGroup(): ?string
    {
        return WebsiteNavigationGroups::label(WebsiteNavigationGroups::PUBLISHING);
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    /**
     * Sin badge, a propósito: esta pantalla **es** el registro de las publicaciones. Un número de
     * «cambios sin publicar» acá repetiría el de las páginas que los editan sin decir cuál, y
     * duplicar un número es la forma de que dos números distintos no coincidan nunca.
     */

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $slug = 'publication-history';

    protected string $view = 'website::filament.pages.publication-history';

    public static function canAccess(): bool
    {
        return parent::canAccess() && Site::forPanel() !== null;
    }

    public static function getNavigationLabel(): string
    {
        return __('website.publication_history.label');
    }

    public function getTitle(): string
    {
        return __('website.publication_history.label');
    }

    /** @return array<class-string> */
    public function getHeaderWidgets(): array
    {
        return [UnpublishedChangesWidget::class];
    }

    public function table(Table $table): Table
    {
        $siteId = Site::forPanelOrFail()->id;

        return $table
            ->query(
                ActivityLogEntry::query()
                    ->where('log_name', StaticSitePublicationService::PUBLICATION_LOG)
                    ->where('site_id', $siteId)
                    ->orderByDesc('created_at')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('website.publication_history.fields.when'))
                    ->formatStateUsing(fn ($state): ?string => $state?->locale(app()->getLocale())->translatedFormat('d M Y H:i'))
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label(__('website.publication_history.fields.who'))
                    // Una publicación disparada sin sesión —o el resultado que escribe el worker—
                    // legítimamente no tiene actor. Guion, nunca un nombre inventado.
                    ->placeholder('—'),
                TextColumn::make('properties.event')
                    ->label(__('website.publication_history.fields.event'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::human('website.publication_history.events', (string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'accepted' => 'success',
                        'failed'   => 'danger',
                        default    => 'warning',
                    }),
                TextColumn::make('properties.reason')
                    ->label(__('website.publication_history.fields.reason'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('properties.error')
                    ->label(__('website.publication_history.fields.error'))
                    ->placeholder('—')
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->wrap(),
            ])
            // Sin esto Filament usa el estado vacío del **modelo**, y el de `ActivityLogEntry`
            // dice «Geen activity log entries»: texto de otra pantalla, delante de alguien que
            // buscaba publicaciones.
            ->emptyStateHeading(__('website.publication_history.empty'))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }

    /**
     * El valor interno (`requested`) no le dice nada a quien aprueba. Mismo criterio que el
     * resto del clúster: si falta la etiqueta se devuelve el valor, nunca la clave cruda.
     */
    private static function human(string $key, string $value): string
    {
        $full = "{$key}.{$value}";
        $label = __($full);

        return $label === $full ? $value : $label;
    }
}
