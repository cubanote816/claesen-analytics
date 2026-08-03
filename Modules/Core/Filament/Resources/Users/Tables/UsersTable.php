<?php

namespace Modules\Core\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

use Filament\Tables\Columns\TextColumn;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('users/resource.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('users/resource.fields.status'))
                    ->getStateUsing(fn($record) => $record->isOnline() ? __('users/resource.fields.online') : __('users/resource.fields.offline'))
                    ->badge()
                    ->color(fn($state) => $state === __('users/resource.fields.online') ? 'success' : 'gray'),
                TextColumn::make('last_active_at')
                    ->label(__('users/resource.fields.last_active_at'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
                TextColumn::make('last_login_at')
                    ->label(__('users/resource.fields.last_login_at'))
                    ->getStateUsing(fn ($record) => $record->last_login_at ?? $record->last_active_at)
                    ->dateTime()
                    ->since()
                    ->sortable(),
                TextColumn::make('last_login_app_source')
                    ->label(__('users/resource.fields.last_login_app_source'))
                    ->getStateUsing(fn ($record) => $record->last_login_app_source ?: __('users/resource.fields.unknown_source'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('last_login_channel')
                    ->label(__('users/resource.fields.last_login_channel'))
                    ->getStateUsing(fn ($record) => $record->last_login_channel ?: __('users/resource.fields.unknown_channel'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('email')
                    ->label(__('users/resource.fields.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label(__('users/resource.fields.roles'))
                    ->formatStateUsing(fn($state) => \Illuminate\Support\Str::headline($state))
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
