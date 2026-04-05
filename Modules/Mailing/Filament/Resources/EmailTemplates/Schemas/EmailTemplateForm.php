<?php

namespace Modules\Mailing\Filament\Resources\EmailTemplates\Schemas;

use Filament\Schemas\Schema;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make(__('mailing::resource.sections.template_details'))
                    ->components([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->label(__('mailing::resource.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        \Filament\Forms\Components\TextInput::make('subject')
                            ->label(__('mailing::resource.fields.subject'))
                            ->required()
                            ->maxLength(255),
                    ]),
                \Filament\Schemas\Components\Section::make(__('mailing::resource.sections.content'))
                    ->description(__('mailing::resource.sections.content_desc'))
                    ->components([
                        \Filament\Forms\Components\RichEditor::make('body')
                            ->label(__('mailing::resource.fields.body'))
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
