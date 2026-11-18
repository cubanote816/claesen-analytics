<?php

declare(strict_types=1);

namespace App\Filament\Bertels\Resources\Users\Schemas;

use App\Filament\Bertels\Resources\Users\BertelsUserResource;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BertelsCreateUserForm
{
    public static function configure(Schema $schema): Schema
    {
        $domain = BertelsUserResource::microsoftEmailDomain() ?? '';

        return $schema
            ->components([
                Section::make()
                    ->schema([
                        Select::make('account_type')
                            ->label(__('users/resource.fields.account_type'))
                            ->options([
                                'microsoft' => __('users/resource.fields.microsoft_account'),
                                'local' => __('users/resource.fields.local_account'),
                            ])
                            ->default('microsoft')
                            ->required()
                            ->live(),

                        TextInput::make('name')
                            ->label(__('users/resource.fields.name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('users/resource.fields.email'))
                            ->helperText(__('users/resource.fields.bertels_email_hint', ['domain' => $domain]))
                            ->email()
                            ->required()
                            ->maxLength(255),

                        // role_ids: plain array so handleRecordCreation() can syncRoles() inside
                        // its transaction. Only viewer/admin are offered (and enforced server-side).
                        CheckboxList::make('role_ids')
                            ->label(__('users/resource.fields.roles'))
                            ->options(fn (): array => BertelsUserResource::assignableRoleOptions())
                            ->columns(2)
                            ->gridDirection('row')
                            ->required(),
                    ]),
            ]);
    }
}
