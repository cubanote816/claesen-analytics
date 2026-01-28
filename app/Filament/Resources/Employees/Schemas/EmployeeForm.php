<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Models\Employee;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Header Information (Non-editable quick glance)
                Section::make()
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('avatar')
                                    ->label(false)
                                    ->collection('avatar')
                                    ->avatar()
                                    ->multiple()
                                    ->maxFiles(1)
                                    ->reorderable()
                                    ->imageEditor()
                                    ->extraAttributes(['class' => 'ring-4 ring-primary-500/10 shadow-lg']),

                                Grid::make(1)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(__('employees/resource.fields.name'))
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->extraAttributes(['class' => 'font-bold']),

                                        TextInput::make('function')
                                            ->label(__('employees/resource.fields.job_function'))
                                            ->disabled()
                                            ->dehydrated(false),
                                    ])
                                    ->columnSpan(2),
                            ])
                            ->extraAttributes(['class' => 'items-center']),
                    ])
                    ->extraAttributes(['class' => 'bg-white/50 backdrop-blur-sm shadow-sm ring-1 ring-gray-950/5 rounded-3xl p-6']),

                // Main Form Content (Editable)
                Section::make(__('employees/resource.fields.personal_information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->label(__('employees/resource.fields.email'))
                                    ->email()
                                    ->maxLength(255),
                                TextInput::make('mobile')
                                    ->label(__('employees/resource.fields.mobile'))
                                    ->maxLength(255),

                                Textarea::make('notes')
                                    ->label(__('employees/resource.fields.notes'))
                                    ->rows(5)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->compact(),
            ]);
    }
}
