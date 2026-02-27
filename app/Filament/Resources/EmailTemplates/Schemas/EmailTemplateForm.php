<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use Filament\Schemas\Schema;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Sjabloon Details')
                    ->components([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label('Naam van Sjabloon')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        \Filament\Forms\Components\TextInput::make('subject')
                            ->label('Onderwerp (E-mail Subject)')
                            ->required()
                            ->maxLength(255),
                    ]),
                \Filament\Schemas\Components\Section::make('Inhoud')
                    ->description('Je kunt variabelen gebruiken zoals {{ naam }} en {{ regio }}')
                    ->components([
                        \Filament\Forms\Components\RichEditor::make('body')
                            ->label('E-mail Bericht')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'link',
                                'h2',
                                'h3',
                                'bulletList',
                                'orderedList',
                                'redo',
                                'undo',
                            ]),
                    ]),
            ]);
    }
}
