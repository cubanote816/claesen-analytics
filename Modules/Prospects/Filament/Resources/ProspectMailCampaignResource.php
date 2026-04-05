<?php

namespace Modules\Prospects\Filament\Resources;

use Modules\Prospects\Models\ProspectMailCampaign;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\ViewAction;

class ProspectMailCampaignResource extends Resource
{
    protected static ?string $model = ProspectMailCampaign::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    public static function getNavigationGroup(): ?string
    {
        return __('prospects::resource.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('prospects::resource.mail_campaign.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('prospects::resource.mail_campaign.plural_model_label');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label(__('prospects::resource.fields.description'))
                    ->searchable()
                    ->wrap(),

                TextColumn::make('template_name')
                    ->label(__('prospects::resource.fields.template'))
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label(__('prospects::resource.fields.started_by'))
                    ->sortable(),

                TextColumn::make('total_count')
                    ->label(__('prospects::resource.fields.total_count'))
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('success_count')
                    ->label(__('prospects::resource.fields.success_count'))
                    ->badge()
                    ->color('success')
                    ->alignCenter(),

                TextColumn::make('failed_count')
                    ->label(__('prospects::resource.fields.failed_count'))
                    ->badge()
                    ->color('danger')
                    ->alignCenter(),

                TextColumn::make('skipped_count')
                    ->label(__('prospects::resource.fields.skipped_count'))
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),

                TextColumn::make('status')
                    ->label(__('prospects::resource.fields.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'processing' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'processing' => __('prospects::resource.options.status.running'),
                        'completed' => __('prospects::resource.options.status.completed'),
                        'failed' => __('prospects::resource.options.status.failed'),
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->label(__('prospects::resource.fields.started_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('prospects::resource.sections.campaign_summary'))
                    ->components([
                        Grid::make(3)->components([
                            TextEntry::make('description')
                                ->label(__('prospects::resource.fields.description'))
                                ->columnSpanFull(),
                            TextEntry::make('template_name')
                                ->label(__('prospects::resource.fields.template')),
                            TextEntry::make('total_count')
                                ->label(__('prospects::resource.fields.total_count')),
                            TextEntry::make('status')
                                ->label(__('prospects::resource.fields.status'))
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'processing' => 'warning',
                                    'completed' => 'success',
                                    'failed' => 'danger',
                                    default => 'gray',
                                }),
                        ]),
                    ]),

                Section::make(__('prospects::resource.sections.snapshot'))
                    ->description(__('prospects::resource.sections.snapshot_desc'))
                    ->collapsed()
                    ->components([
                        TextEntry::make('subject_snapshot')
                            ->label(__('prospects::resource.fields.subject')),
                        TextEntry::make('body_snapshot')
                            ->label(__('prospects::resource.fields.body'))
                            ->html(),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProspectMailCampaignResource\RelationManagers\LogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ProspectMailCampaignResource\Pages\ListProspectMailCampaigns::route('/'),
            'view' => ProspectMailCampaignResource\Pages\ViewProspectMailCampaign::route('/{record}'),
        ];
    }
}
