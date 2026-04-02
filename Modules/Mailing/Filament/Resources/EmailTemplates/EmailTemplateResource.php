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
