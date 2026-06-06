<?php

namespace Modules\Mailing\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Filament\Resources\CampaignResource\Pages;
use Modules\Mailing\Filament\Resources\CampaignResource\RelationManagers\MessagesRelationManager;
use Modules\Mailing\Filament\Resources\CampaignResource\Schemas\CampaignForm;
use Modules\Mailing\Models\Campaign;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    public static function getNavigationGroup(): ?string
    {
        return 'Mailing';
    }

    protected static ?int $navigationSort = 1;

    public static function getModelLabel(): string
    {
        return __('mailing::resource.campaign.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('mailing::resource.campaign.plural_model_label');
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function form(Schema $schema): Schema
    {
        return CampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->label(__('mailing::resource.fields.description'))
                    ->searchable()
                    ->wrap()
                    ->limit(60),

                TextColumn::make('template_name')
                    ->label(__('mailing::resource.fields.template'))
                    ->searchable(),

                TextColumn::make('status')
                    ->label(__('mailing::resource.fields.status'))
                    ->badge()
                    ->color(fn (CampaignStatus $state): string => $state->color())
                    ->formatStateUsing(fn (CampaignStatus $state): string => $state->label()),

                TextColumn::make('total_count')
                    ->label(__('mailing::resource.fields.total_count'))
                    ->numeric()
                    ->alignCenter(),

                TextColumn::make('sent_count')
                    ->label(__('mailing::resource.fields.success_count'))
                    ->badge()
                    ->color('success')
                    ->alignCenter(),

                TextColumn::make('failed_count')
                    ->label(__('mailing::resource.fields.failed_count'))
                    ->badge()
                    ->color('danger')
                    ->alignCenter(),

                TextColumn::make('creator.name')
                    ->label(__('mailing::resource.fields.started_by'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('mailing::resource.fields.started_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('mailing::resource.fields.status'))
                    ->options(collect(CampaignStatus::cases())->mapWithKeys(
                        fn (CampaignStatus $s) => [$s->value => $s->label()]
                    )),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),

                Action::make('submit')
                    ->label(__('mailing::resource.actions.submit_review'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    // Same guard as Approve — Gate::before bypasses policy for super_admin,
                    // so without canTransitionTo() the button appears on failed/completed campaigns.
                    ->visible(fn (Campaign $record): bool =>
                        $record->canTransitionTo(CampaignStatus::REVIEW)
                        && (auth()->user()?->can('submit', $record) ?? false)
                    )
                    ->requiresConfirmation()
                    ->action(function (Campaign $record): void {
                        $record->transitionTo(CampaignStatus::REVIEW);
                        Notification::make()
                            ->title(__('mailing::resource.notifications.submitted_review'))
                            ->success()
                            ->send();
                    }),

                Action::make('approve')
                    ->label(__('mailing::resource.actions.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    // canTransitionTo() guards against Gate::before bypassing the policy for super_admin.
                    // Without it, super_admin sees Approve on completed/cancelled campaigns and gets a DomainException.
                    ->visible(fn (Campaign $record): bool =>
                        $record->canTransitionTo(CampaignStatus::APPROVED)
                        && (auth()->user()?->can('approve', $record) ?? false)
                    )
                    ->requiresConfirmation()
                    ->action(function (Campaign $record): void {
                        $record->transitionTo(CampaignStatus::APPROVED, auth()->id());
                        Notification::make()
                            ->title(__('mailing::resource.notifications.approved'))
                            ->success()
                            ->send();
                    }),

                Action::make('cancel')
                    ->label(__('mailing::resource.actions.cancel'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Campaign $record): bool =>
                        $record->canTransitionTo(CampaignStatus::CANCELLED)
                        && (auth()->user()?->can('cancel', $record) ?? false)
                    )
                    ->requiresConfirmation()
                    ->action(function (Campaign $record): void {
                        $record->transitionTo(CampaignStatus::CANCELLED);
                        Notification::make()
                            ->title(__('mailing::resource.notifications.cancelled'))
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('mailing::resource.sections.campaign_summary'))
                    ->components([
                        Grid::make(3)->components([
                            TextEntry::make('description')
                                ->label(__('mailing::resource.fields.description'))
                                ->columnSpanFull(),

                            TextEntry::make('template_name')
                                ->label(__('mailing::resource.fields.template')),

                            TextEntry::make('status')
                                ->label(__('mailing::resource.fields.status'))
                                ->badge()
                                ->color(fn (CampaignStatus $state): string => $state->color())
                                ->formatStateUsing(fn (CampaignStatus $state): string => $state->label()),

                            TextEntry::make('creator.name')
                                ->label(__('mailing::resource.fields.started_by')),

                            TextEntry::make('total_count')
                                ->label(__('mailing::resource.fields.total_count'))
                                ->numeric(),

                            TextEntry::make('sent_count')
                                ->label(__('mailing::resource.fields.success_count'))
                                ->badge()
                                ->color('success'),

                            TextEntry::make('failed_count')
                                ->label(__('mailing::resource.fields.failed_count'))
                                ->badge()
                                ->color('danger'),
                        ]),
                    ]),

                Section::make(__('mailing::resource.sections.approval'))
                    ->visible(fn (Campaign $record): bool => $record->approved_by !== null)
                    ->components([
                        Grid::make(2)->components([
                            TextEntry::make('approver.name')
                                ->label(__('mailing::resource.fields.approved_by')),
                            TextEntry::make('approved_at')
                                ->label(__('mailing::resource.fields.approved_at'))
                                ->dateTime(),
                        ]),
                    ]),

                Section::make(__('mailing::resource.sections.snapshot'))
                    ->description(__('mailing::resource.sections.snapshot_desc'))
                    ->components([
                        TextEntry::make('subject_snapshot')
                            ->label(__('mailing::resource.fields.subject'))
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('body_snapshot_preview')
                            ->label(__('mailing::resource.fields.body_preview'))
                            ->getStateUsing(fn (Campaign $record): string =>
                                Str::limit(strip_tags($record->body_snapshot ?? ''), 350)
                            )
                            ->placeholder(__('mailing::resource.fields.body_empty'))
                            ->columnSpanFull(),

                        Section::make(__('mailing::resource.sections.full_content'))
                            ->collapsible()
                            ->collapsed()
                            ->visible(fn (Campaign $record): bool => filled($record->body_snapshot))
                            ->components([
                                TextEntry::make('body_snapshot')
                                    ->label('')
                                    ->html()
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [MessagesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCampaigns::route('/'),
            'create' => Pages\CreateCampaign::route('/create'),
            'view'   => Pages\ViewCampaign::route('/{record}'),
        ];
    }
}
