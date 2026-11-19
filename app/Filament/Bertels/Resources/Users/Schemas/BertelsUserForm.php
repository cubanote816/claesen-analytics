<?php

declare(strict_types=1);

namespace App\Filament\Bertels\Resources\Users\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Resources\Users\Schemas\UserForm;

/**
 * CLA-704 S10: the very same fields as Claesen's edit form (name, editable unique email,
 * organization, active toggle, read-only roles) minus the FieldOps client fields, which
 * Bertels does not have. The Microsoft/local boundary of the e-mail is enforced
 * server-side by EditBertelsUser.
 */
class BertelsUserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema(UserForm::commonComponents()),
            ]);
    }
}
