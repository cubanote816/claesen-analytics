<?php

declare(strict_types=1);

namespace App\Filament\Clusters\Website\Resources;

use App\Filament\Clusters\Website\Resources\MediaSlotResource\Pages;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Concerns\ScopedToPanelSite;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Core\Models\Site;
use Modules\Website\Models\MediaSlot;
use Modules\Website\Models\Project;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * CLA-481: assign site media to named slots (home.hero, over-ons.team, …)
 * that the frontend build fetches via GET /v1/website/media/slots.
 *
 * Site-scoped with ScopedToPanelSite (CLA-598): only the rows of the site
 * the current panel manages, fail-closed. alt/caption per locale are NOT
 * edited here — they live on the media item's custom properties and are
 * AI-generated/translated by GenerateGalleryMediaMetadataJob (CLA-467/470).
 */
class MediaSlotResource extends Resource
{
    use ScopedToPanelSite;

    protected static ?string $model = MediaSlot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $cluster = WebsiteCluster::class;

    public static function getNavigationLabel(): string
    {
        return __('website.media_slots.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('website.media_slots.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('website.media_slots.plural_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('slot')
                ->label(__('website.media_slots.fields.slot'))
                ->required()
                ->maxLength(100)
                ->datalist(MediaSlot::SUGGESTED_SLOTS)
                ->helperText(__('website.media_slots.fields.slot_helper'))
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn ($rule) => $rule->where('site_id', Site::forPanel()?->id ?? 0)
                ),
            Select::make('media_id')
                ->label(__('website.media_slots.fields.media'))
                ->required()
                ->searchable()
                ->options(fn (): array => static::mediaOptions())
                ->rule(static function (): \Closure {
                    // The select lists this panel site's Project media, but
                    // never trust the posted id: the validation rule is the
                    // real boundary, independent of the option list.
                    return function (string $attribute, mixed $value, \Closure $fail): void {
                        $belongsToPanelSite = Media::query()
                            ->whereKey($value)
                            ->where('model_type', Project::class)
                            ->whereIn('model_id', Project::query()
                                ->select('id')
                                ->where('site_id', Site::forPanel()?->id ?? 0))
                            ->exists();

                        if (! $belongsToPanelSite) {
                            $fail(__('website.media_slots.fields.media_invalid'));
                        }
                    };
                }),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function mediaOptions(): array
    {
        $siteId = Site::forPanel()?->id;

        if ($siteId === null) {
            return [];
        }

        // La media de un slot puede ser de un proyecto o del propio sitio: el sitio es el dueño
        // de su imaginería (equipo, certificados, diagrama) y antes no había forma de elegirla
        // desde aquí, aunque el modelo lo permita.
        $projectIds = Project::query()->select('id')->where('site_id', $siteId);

        return Media::query()
            ->where(fn ($query) => $query
                ->where(fn ($inner) => $inner->where('model_type', Project::class)->whereIn('model_id', $projectIds))
                ->orWhere(fn ($inner) => $inner->where('model_type', Site::class)->where('model_id', $siteId)))
            ->orderBy('id', 'desc')
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Media $media) => [
                $media->id => "#{$media->id} {$media->name} ({$media->file_name})",
            ])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slot')
                    ->label(__('website.media_slots.fields.slot'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('media.name')
                    ->label(__('website.media_slots.fields.media')),
                Tables\Columns\TextColumn::make('media.file_name'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('slot')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMediaSlots::route('/'),
            'create' => Pages\CreateMediaSlot::route('/create'),
            'edit' => Pages\EditMediaSlot::route('/{record}/edit'),
        ];
    }
}
