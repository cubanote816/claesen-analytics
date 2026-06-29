<?php

namespace Modules\Cafca\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        $isNl = app()->getLocale() === 'nl';

        return $schema
            ->components([
                Grid::make(['default' => 1, 'sm' => 2])
                    ->columnSpanFull()
                    ->schema([

                        // ── Left: Identity (readonly context) ────────────────
                        Section::make($isNl ? 'Medewerker' : 'Employee')
                            ->description($isNl ? 'Gegevens gesynchroniseerd vanuit ERP.' : 'Data synchronised from ERP — read-only.')
                            ->schema([
                                \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('avatar')
                                    ->label($isNl ? 'Profielfoto' : 'Profile photo')
                                    ->collection('avatar')
                                    ->image()
                                    ->multiple()
                                    ->maxFiles(1)
                                    ->imageEditor()
                                    ->helperText($isNl ? 'JPG of PNG, max 2 MB' : 'JPG or PNG, max 2 MB'),

                                TextInput::make('name')
                                    ->label($isNl ? 'Naam' : 'Full name')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('function')
                                    ->label($isNl ? 'Functie' : 'Job function')
                                    ->disabled()
                                    ->dehydrated(false),
                            ])
                            ->columnSpan(1),

                        // ── Right: Editable contact details ──────────────────
                        Section::make(__('employees/resource.fields.contact_details'))
                            ->description($isNl ? 'Contactgegevens kunnen worden bijgewerkt.' : 'Contact details can be updated directly.')
                            ->schema([
                                TextInput::make('email')
                                    ->label(__('employees/resource.fields.email'))
                                    ->email()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-envelope'),

                                TextInput::make('mobile')
                                    ->label(__('employees/resource.fields.mobile'))
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-phone'),

                                Textarea::make('notes')
                                    ->label(__('employees/resource.fields.notes'))
                                    ->rows(5)
                                    ->helperText($isNl ? 'Interne notities, zichtbaar alleen voor beheerders.' : 'Internal notes, visible to admins only.'),
                            ])
                            ->columnSpan(1),

                    ]),
            ]);
    }
}
