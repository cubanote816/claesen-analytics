<?php

namespace Modules\Mailing\Filament\Resources\EmailTemplates\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Modules\Mailing\Enums\TemplateCategory;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // items-start: each column grows independently — right panel never
                // creates vertical gaps in the left editing column.
                Grid::make(3)
                    ->columnSpanFull()
                    ->extraAttributes(['class' => 'items-start'])
                    ->components([

                        // Left 2/3 — editing area
                        Group::make([
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
                                        ->required()
                                        ->live(),

                                    Select::make('preference_category')
                                        ->label(__('mailing::resource.fields.preference_category'))
                                        ->helperText(__('mailing::resource.fields.preference_category_helper'))
                                        ->options(fn () => collect(config('mailing.preference_categories', []))
                                            ->mapWithKeys(fn ($label, $key) => [$key => $label])
                                            ->toArray()
                                        )
                                        ->nullable()
                                        ->visible(fn (Get $get): bool => $get('category') === TemplateCategory::COMMERCIAL->value)
                                        ->required(fn (Get $get): bool => $get('category') === TemplateCategory::COMMERCIAL->value),

                                    TextInput::make('subject')
                                        ->label(__('mailing::resource.fields.subject'))
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpanFull(),
                                ])
                                ->columns(2),

                            Section::make(__('mailing::resource.sections.content'))
                                ->description(__('mailing::resource.sections.content_desc'))
                                ->components([
                                    \Filament\Forms\Components\RichEditor::make('body')
                                        ->label(__('mailing::resource.fields.body'))
                                        ->required()
                                        ->columnSpanFull()
                                        ->toolbarButtons([
                                            'bold', 'italic', 'underline', 'strike',
                                            'link', 'h2', 'h3', 'bulletList',
                                            'orderedList', 'redo', 'undo',
                                        ]),
                                ]),
                        ])->columnSpan(2),

                        // Right 1/3 — reference panel (independent height, no left-side gap)
                        Section::make(__('mailing::resource.sections.variables'))
                            ->description(__('mailing::resource.sections.variables_desc'))
                            ->collapsed()
                            ->columnSpan(1)
                            ->components([
                                View::make('mailing::filament.system-variables-reference')
                                    ->columnSpanFull(),

                                Repeater::make('variables')
                                    ->label(__('mailing::resource.fields.variables'))
                                    ->helperText(__('mailing::resource.fields.variables_helper'))
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
                                    ->columns(1)
                                    ->defaultItems(0)
                                    ->addActionLabel(__('mailing::resource.actions.add_variable'))
                                    ->reorderable()
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
