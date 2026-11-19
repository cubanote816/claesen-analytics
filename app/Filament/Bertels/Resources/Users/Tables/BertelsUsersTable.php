<?php

declare(strict_types=1);

namespace App\Filament\Bertels\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BertelsUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('users/resource.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('users/resource.fields.email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label(__('users/resource.fields.roles'))
                    ->formatStateUsing(fn ($state) => Str::headline($state))
                    ->badge(),
                IconColumn::make('is_active')
                    ->label(__('users/resource.fields.is_active'))
                    ->boolean(),
                TextColumn::make('last_login_at')
                    ->label(__('users/resource.fields.last_login_at'))
                    ->getStateUsing(fn ($record) => $record->last_login_at ?? $record->last_active_at)
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
