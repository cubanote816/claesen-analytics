<?php

declare(strict_types=1);

namespace App\Filament\Bertels\Resources\Users;

use App\Filament\Bertels\Resources\Users\Pages\CreateBertelsUser;
use App\Filament\Bertels\Resources\Users\Pages\EditBertelsUser;
use App\Filament\Bertels\Resources\Users\Pages\ListBertelsUsers;
use App\Filament\Bertels\Resources\Users\Schemas\BertelsUserForm;
use App\Filament\Bertels\Resources\Users\Tables\BertelsUsersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Resources\Users\Schemas\CreateUserForm;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Spatie\Permission\Models\Role;

/**
 * CLA-704: the Users section of the Electro Bertels panel — only for `super_admin`,
 * and only ever over the Bertels organization's users.
 *
 * The boundary is explicit and independent of ORGANIZATIONS_ENFORCE (off in production):
 * every query and every record lookup carries `organization_id = <Bertels>`, so a Claesen
 * user's id in the URL is a 404, and users are only created inside that organization.
 * Fail-closed: with no Bertels site/organization the section is unavailable and matches
 * nothing; it never falls back to Claesen (same rule as ScopedToPanelSite).
 */
class BertelsUserResource extends Resource
{
    protected static ?string $model = User::class;

    // Same path as Claesen's Users resource, in the other panel.
    protected static ?string $slug = 'users';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    /** The organization that owns the panel's site, or null when that site does not exist. */
    public static function organizationId(): ?int
    {
        return Site::forPanel('bertels')?->organization_id;
    }

    /** E-mail domain whose addresses are Microsoft accounts, or null when none is configured. */
    public static function microsoftEmailDomain(): ?string
    {
        $domain = config('organizations.microsoft_email_domains.'.config('organizations.panel_sites.bertels'));

        return is_string($domain) && $domain !== '' ? strtolower($domain) : null;
    }

    /**
     * S10/L9: the roles a Bertels user can never be given — the very list Claesen applies
     * to its External workers (every role but super_admin and client).
     *
     * @return array<int, string>
     */
    public static function forbiddenRoleNames(): array
    {
        return CreateUserForm::EXTERNAL_FORBIDDEN_ROLES;
    }

    /**
     * Names of the roles that can be assigned (everything not forbidden): the server-side
     * allowlist of change-roles.
     *
     * @return array<int, string>
     */
    public static function assignableRoleNames(): array
    {
        return Role::query()->whereNotIn('name', self::forbiddenRoleNames())->pluck('name')->all();
    }

    /**
     * Checkbox options: Claesen's External worker list, same order.
     *
     * @return array<int, string> role id => headline name
     */
    public static function assignableRoleOptions(): array
    {
        return CreateUserForm::roleOptions('external');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return $query->where($query->getModel()->qualifyColumn('organization_id'), static::organizationId() ?? 0);
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->hasRole('super_admin') ?? false) && static::organizationId() !== null;
    }

    // Every ability collapses into canAccess(): nothing in this section is usable by anyone else.
    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canAccess();
    }

    // S10: delete exactly like Claesen, but only ever over this organization's users.
    public static function canDelete(Model $record): bool
    {
        return static::canAccess()
            && $record->getAttribute('organization_id') === static::organizationId();
    }

    public static function canDeleteAny(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('navigation.groups.user_management');
    }

    public static function getModelLabel(): string
    {
        return __('users/resource.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('users/resource.plural_model_label');
    }

    public static function form(Schema $schema): Schema
    {
        return BertelsUserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BertelsUsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBertelsUsers::route('/'),
            'create' => CreateBertelsUser::route('/create'),
            'edit' => EditBertelsUser::route('/{record}/edit'),
        ];
    }
}
