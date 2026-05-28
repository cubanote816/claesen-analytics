<?php

namespace Modules\Mailing\Filament\Resources\EmailTemplates\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Mailing\Enums\TemplateCategory;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('mailing::resource.sections.template_details'))
                    ->components([
                        TextInput::make('name')
                            ->label(__('mailing::resource.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Select::make('category')
                            ->label(__('mailing::resource.fields.category'))
                            ->options(collect(TemplateCategory::cases())->mapWithKeys(
                                fn (TemplateCategory $c) => [$c->value => $c->label()]
                            ))
                            ->default(TemplateCategory::COMMERCIAL->value)
                            ->required(),

                        TextInput::make('subject')
                            ->label(__('mailing::resource.fields.subject'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('mailing::resource.sections.variables'))
                    ->description(__('mailing::resource.sections.variables_desc'))
                    ->collapsed()
                    ->components([
                        Repeater::make('variables')
                            ->label(__('mailing::resource.fields.variables'))
                            ->schema([
                                TextInput::make('key')
                                    ->label(__('mailing::resource.fields.variable_key'))
                                    ->required()
                                    ->alphaDash()
                                    ->maxLength(50),
                                TextInput::make('label')
                                    ->label(__('mailing::resource.fields.variable_label'))
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('example')
                                    ->label(__('mailing::resource.fields.variable_example'))
                                    ->maxLength(150),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->addActionLabel(__('mailing::resource.actions.add_variable'))
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),

                Section::make(__('mailing::resource.sections.content'))
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
