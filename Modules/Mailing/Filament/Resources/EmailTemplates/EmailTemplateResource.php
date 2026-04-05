<?php

namespace Modules\Mailing\Filament\Resources\EmailTemplates;

use Modules\Mailing\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use Modules\Mailing\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use Modules\Mailing\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use Modules\Mailing\Filament\Resources\EmailTemplates\Schemas\EmailTemplateForm;
use Modules\Mailing\Filament\Resources\EmailTemplates\Tables\EmailTemplatesTable;
use Modules\Mailing\Models\EmailTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('mailing::resource.navigation_group');
    }

    public static function getModelLabel(): string
    {
        return __('mailing::resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('mailing::resource.plural_model_label');
    }

    protected static ?int $navigationSort = 2;
    
    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'email-templates';

    public static function form(Schema $schema): Schema
    {
        return EmailTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailTemplatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'create' => CreateEmailTemplate::route('/create'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
