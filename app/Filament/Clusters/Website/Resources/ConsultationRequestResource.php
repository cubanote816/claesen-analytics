<?php

namespace App\Filament\Clusters\Website\Resources;

use App\Filament\Clusters\Website\WebsiteCluster;
use App\Filament\Clusters\Website\Resources\ConsultationRequestResource\Pages;
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
                Section::make('Contact Information')
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('email')->email()->required(),
                        TextInput::make('phone'),
                        TextInput::make('company'),
                    ])->columns(2),

                Section::make('Request Details')
                    ->schema([
                        Select::make('type')
                            ->options([
                                'consultation' => 'Consultation',
                                'quote' => 'Quote',
                                'project' => 'Project',
                            ])->required(),
                        Select::make('project_type')
                            ->options([
                                'sport' => 'Sport',
                                'industrial' => 'Industrial',
                                'public' => 'Public',
                                'masts' => 'Masts',
                                'other' => 'Other',
                            ]),
                        Textarea::make('message')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Internal Management')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'contacted' => 'Contacted',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])->required(),
                        Select::make('priority')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                                'urgent' => 'Urgent',
                            ]),
                        Select::make('assigned_to')
                            ->relationship('assignedUser', 'name'),
                        DatePicker::make('follow_up_date'),
                        Textarea::make('internal_notes'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'gray',
                        'contacted' => 'info',
                        'in_progress' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'low' => 'gray',
                        'medium' => 'info',
                        'high' => 'warning',
                        'urgent' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'contacted' => 'Contacted',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('type'),
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\EditAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Infolists\Section::make('Contact')
                    ->schema([
                        Infolists\Text::make('name'),
                        Infolists\Text::make('email'),
                        Infolists\Text::make('phone'),
                        Infolists\Text::make('company'),
                    ])->columns(2),
                Infolists\Section::make('Message')
                    ->schema([
                        Infolists\Text::make('type'),
                        Infolists\Text::make('project_type'),
                        Infolists\Text::make('message')->columnSpanFull(),
                    ])->columns(2),
                Infolists\Section::make('Status')
                    ->schema([
                        Infolists\Text::make('status')->badge(),
                        Infolists\Text::make('priority')->badge(),
                        Infolists\Text::make('assignedUser.name')->label('Assigned To'),
                    ])->columns(3),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsultationRequests::route('/'),
            'create' => Pages\CreateConsultationRequest::route('/create'), // Optional
            'view' => Pages\ViewConsultationRequest::route('/{record}'),
            'edit' => Pages\EditConsultationRequest::route('/{record}/edit'),
        ];
    }
}
