<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Cafca\Employee;
use Filament\Forms\Components\TextInput; // Inputs namespace
use Filament\Schemas\Components\Grid; // Layouts namespace
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema; // Unified Schema
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    public static function getNavigationLabel(): string
    {
        return (__('employees/resource.navigation_label'));
    }

    public static function getModelLabel(): string
    {
        return __('employees/resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('employees/resource.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('employees/resource.sections.personal_info'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('first_name')
                                    ->label(__('employees/resource.fields.first_name'))
                                    ->disabled(), // Read-only
                                TextInput::make('last_name')
                                    ->label(__('employees/resource.fields.last_name'))
                                    ->disabled(),
                                TextInput::make('ref_id')
                                    ->label(__('employees/resource.fields.ref_id'))
                                    ->disabled(),
                                TextInput::make('job_title')
                                    ->label(__('employees/resource.fields.job_title'))
                                    ->disabled(),
                            ]),
                    ]),
                Section::make(__('employees/resource.sections.contact_details'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('email')
                                    ->label(__('employees/resource.fields.email'))
                                    ->email()
                                    ->disabled(),
                                TextInput::make('mobile')
                                    ->label(__('employees/resource.fields.mobile'))
                                    ->tel()
                                    ->disabled(),
                                TextInput::make('address_combined')
                                    ->label(__('employees/resource.fields.address'))
                                    ->columnSpanFull()
                                    ->disabled(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name') // Assuming accessor or concatenation needed
                    ->label(__('employees/resource.fields.name'))
                    ->getStateUsing(fn(Employee $record) => trim($record->first_name . ' ' . $record->last_name))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->description(fn(Employee $record) => $record->job_title),

                TextColumn::make('email')
                    ->label(__('employees/resource.fields.email'))
                    ->icon('heroicon-m-envelope')
                    ->searchable(),

                TextColumn::make('mobile')
                    ->label(__('employees/resource.fields.phone')),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('employees/resource.fields.status')),
            ])
            ->filters([
                //
            ])
            ->actions([
                \Filament\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for read-only resource ideally, or at least no delete
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
            'index' => Pages\ListEmployees::route('/'),
            'view' => Pages\ViewEmployee::route('/{record}'),
        ];
    }
}
