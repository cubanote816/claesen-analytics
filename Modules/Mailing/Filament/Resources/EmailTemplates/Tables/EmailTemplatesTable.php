<?php

namespace Modules\Mailing\Filament\Resources\EmailTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Mailing\Enums\TemplateCategory;

class EmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('mailing::resource.fields.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category')
                    ->label(__('mailing::resource.fields.category'))
                    ->badge()
                    ->color(fn (TemplateCategory $state): string => $state->color())
                    ->formatStateUsing(fn (TemplateCategory $state): string => $state->label()),

                TextColumn::make('version')
                    ->label(__('mailing::resource.fields.version'))
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('subject')
                    ->label(__('mailing::resource.fields.subject'))
                    ->searchable()
                    ->limit(60),

                TextColumn::make('updated_at')
                    ->label(__('mailing::resource.fields.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label(__('mailing::resource.fields.category'))
                    ->options(collect(TemplateCategory::cases())->mapWithKeys(
                        fn (TemplateCategory $c) => [$c->value => $c->label()]
                    )),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
