<?php

namespace App\Filament\Clusters\Website\Resources;

use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\Resources\ConsultationRequestResource\Pages;
use App\Filament\Clusters\Website\Resources\ConsultationRequestResource\RelationManagers;
use Modules\Website\Models\ConsultationRequest;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components as Infolists;

class ConsultationRequestResource extends Resource
{
    protected static ?string $model = ConsultationRequest::class;

    public static function getNavigationLabel(): string
    {
        return __('website.consultation_requests.plural_label');
    }

    public static function getModelLabel(): string
    {
        return __('website.consultation_requests.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('website.consultation_requests.plural_label');
    }

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-inbox';

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                \Filament\Schemas\Components\Group::make()
                    ->schema([
                        Section::make(__('website.consultation_requests.sections.contact'))
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('website.consultation_requests.fields.name'))
                                    ->required()
                                    ->disabledOn('edit'),
                                TextInput::make('email')
                                    ->label(__('website.consultation_requests.fields.email'))
                                    ->email()
                                    ->required()
                                    ->disabledOn('edit'),
                                TextInput::make('phone')
                                    ->label(__('website.consultation_requests.fields.phone'))
                                    ->disabledOn('edit'),
                                TextInput::make('company')
                                    ->label(__('website.consultation_requests.fields.company'))
                                    ->disabledOn('edit'),
                            ])->columns(2),

                        Section::make(__('website.consultation_requests.sections.details'))
                            ->schema([
                                Select::make('type')
                                    ->label(__('website.consultation_requests.fields.type'))
                                    ->options([
                                        'consultation' => __('website.consultation_requests.types.consultation'),
                                        'quote' => __('website.consultation_requests.types.quote'),
                                        'project' => __('website.consultation_requests.types.project'),
                                    ])->required()->disabledOn('edit'),
                                Select::make('project_type')
                                    ->label(__('website.consultation_requests.fields.project_type'))
                                    ->options([
                                        'sport' => __('website.consultation_requests.project_types.sport'),
                                        'industrial' => __('website.consultation_requests.project_types.industrial'),
                                        'public' => __('website.consultation_requests.project_types.public'),
                                        'masts' => __('website.consultation_requests.project_types.masts'),
                                        'other' => __('website.consultation_requests.project_types.other'),
                                    ])->disabledOn('edit'),
                                Textarea::make('message')
                                    ->label(__('website.consultation_requests.fields.message'))
                                    ->columnSpanFull()->disabledOn('edit'),
                            ])->columns(2),
                    ])
                    ->columnSpan(['lg' => 2]),

                \Filament\Schemas\Components\Group::make()
                    ->schema([
                        Section::make(__('website.consultation_requests.sections.management'))
                            ->schema([
                                Select::make('status')
                                    ->label(__('website.consultation_requests.fields.status'))
                                    ->options([
                                        'pending' => __('website.consultation_requests.status_options.pending'),
                                        'contacted' => __('website.consultation_requests.status_options.contacted'),
                                        'in_progress' => __('website.consultation_requests.status_options.in_progress'),
                                        'completed' => __('website.consultation_requests.status_options.completed'),
                                        'cancelled' => __('website.consultation_requests.status_options.cancelled'),
                                    ])->required(),
                                Select::make('priority')
                                    ->label(__('website.consultation_requests.fields.priority'))
                                    ->options([
                                        'low' => __('website.consultation_requests.priority_options.low'),
                                        'medium' => __('website.consultation_requests.priority_options.medium'),
                                        'high' => __('website.consultation_requests.priority_options.high'),
                                        'urgent' => __('website.consultation_requests.priority_options.urgent'),
                                    ]),
                                Select::make('assigned_to')
                                    ->label(__('website.consultation_requests.fields.assigned_to'))
                                    ->relationship('assignedUser', 'name'),
                                DatePicker::make('follow_up_date')
                                    ->label(__('website.consultation_requests.fields.follow_up_date')),
                                Textarea::make('internal_notes')
                                    ->label(__('website.consultation_requests.fields.internal_notes')),
                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('website.consultation_requests.fields.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('website.consultation_requests.fields.email'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('website.consultation_requests.fields.type'))
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.types.{$state}")),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('website.consultation_requests.fields.status'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'gray',
                        'contacted' => 'info',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.status_options.{$state}")),
                Tables\Columns\TextColumn::make('priority')
                    ->label(__('website.consultation_requests.fields.priority'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'info',
                        'high' => 'warning',
                        'urgent' => 'danger',
                    })
                    ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.priority_options.{$state}")),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('website.activities.fields.date'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('website.consultation_requests.fields.status'))
                    ->options([
                        'pending' => __('website.consultation_requests.status_options.pending'),
                        'contacted' => __('website.consultation_requests.status_options.contacted'),
                        'in_progress' => __('website.consultation_requests.status_options.in_progress'),
                        'completed' => __('website.consultation_requests.status_options.completed'),
                        'cancelled' => __('website.consultation_requests.status_options.cancelled'),
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('website.consultation_requests.fields.type')),
            ])
            ->actions([
                // \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    // \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Infolists\Section::make(__('website.consultation_requests.sections.contact'))
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label(__('website.consultation_requests.fields.name')),
                        \Filament\Infolists\Components\TextEntry::make('email')
                            ->label(__('website.consultation_requests.fields.email')),
                        \Filament\Infolists\Components\TextEntry::make('phone')
                            ->label(__('website.consultation_requests.fields.phone')),
                        \Filament\Infolists\Components\TextEntry::make('company')
                            ->label(__('website.consultation_requests.fields.company')),
                    ])->columns(2),
                Infolists\Section::make(__('website.consultation_requests.sections.message'))
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('type')
                            ->label(__('website.consultation_requests.fields.type'))
                            ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.types.{$state}")),
                        \Filament\Infolists\Components\TextEntry::make('project_type')
                            ->label(__('website.consultation_requests.fields.project_type'))
                            ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.project_types.{$state}")),
                        \Filament\Infolists\Components\TextEntry::make('message')
                            ->label(__('website.consultation_requests.fields.message'))
                            ->columnSpanFull(),
                    ])->columns(2),
                Infolists\Section::make(__('website.consultation_requests.sections.status'))
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('status')
                            ->label(__('website.consultation_requests.fields.status'))
                            ->badge()
                            ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.status_options.{$state}")),
                        \Filament\Infolists\Components\TextEntry::make('priority')
                            ->label(__('website.consultation_requests.fields.priority'))
                            ->badge()
                            ->formatStateUsing(fn(string $state): string => __("website.consultation_requests.priority_options.{$state}")),
                        \Filament\Infolists\Components\TextEntry::make('assignedUser.name')
                            ->label(__('website.consultation_requests.fields.assigned_to')),
                    ])->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsultationRequests::route('/'),
            'create' => Pages\CreateConsultationRequest::route('/create'), // Optional
            'edit' => Pages\EditConsultationRequest::route('/{record}/edit'),
        ];
    }
}
