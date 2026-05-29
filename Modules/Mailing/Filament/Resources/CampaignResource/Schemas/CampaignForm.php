<?php

namespace Modules\Mailing\Filament\Resources\CampaignResource\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Mailing\Models\EmailTemplate;

class CampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('mailing::resource.sections.campaign_details'))
                    ->columns(2)
                    ->components([
                        Select::make('template_id')
                            ->label(__('mailing::resource.fields.template'))
                            ->options(EmailTemplate::orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?int $state, Set $set): void {
                                if ($template = EmailTemplate::find($state)) {
                                    $set('template_name', $template->name);
                                    $set('subject_snapshot', $template->subject);
                                    $set('body_snapshot', $template->body);
                                }
                            })
                            ->columnSpanFull(),

                        TextInput::make('description')
                            ->label(__('mailing::resource.fields.description'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('subject_snapshot')
                            ->label(__('mailing::resource.fields.subject'))
                            ->required()
                            ->maxLength(255)
                            ->helperText(__('mailing::resource.fields.subject_helper'))
                            ->columnSpanFull(),
                    ]),

                // Hidden fields populated from the template picker — not shown to the user
                \Filament\Forms\Components\Hidden::make('template_name'),
                \Filament\Forms\Components\Hidden::make('body_snapshot'),
            ]);
    }
}
