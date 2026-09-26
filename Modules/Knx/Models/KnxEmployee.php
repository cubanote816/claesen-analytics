<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Organization;
use Modules\Core\Models\User;
use Modules\Knx\Database\Factories\KnxEmployeeFactory;

/**
 * A person of the KNX domain: office staff (Kantoor) or field technician (Veld).
 *
 * This is the row every human reference in the contract resolves to — the
 * project lead, who registered a device, who reported a conflict, who updated a
 * zone check, who wrote a functional spec. The contract exposes all of them as
 * display strings ("L. Smet"); those strings are now *derived* from here instead
 * of duplicated as free text, so renaming a person does not leave stale names
 * behind in five tables.
 *
 * Two different "roles" live here and they must not be confused:
 *   - `knx_role` (lead / planner / technician / admin) is the *business* role the
 *     contract returns as `Session.role`. It says what someone does.
 *   - the Spatie role (knx_office / knx_field) is *app access*. It says which app
 *     they may open. See RolesAndPermissionsSeeder.
 *
 * Neither grants the Filament panels: Kantoor and Veld are their own apps, so
 * `hasPanelAccess()` returns false for these roles and EnsurePanelAccess sends
 * them to /auth/no-access. (They can still *start* a panel session — the same
 * behaviour project_manager has had since CLA-205: the gate is the middleware,
 * not canAccessPanel(), so a stale session can always log out.)
 */
class KnxEmployee extends Model
{
    use HasFactory;

    /** Office staff — the Kantoor app. */
    public const KIND_OFFICE = 'office';

    /** Field technician — the Veld app. */
    public const KIND_FIELD = 'field';

    /**
     * Business roles, as sent in `Session.role`. The front owns the labels
     * ("lead" → "Projectleider"), so these stay untranslated strings.
     */
    public const ROLES = ['lead', 'planner', 'technician', 'admin'];

    protected $table = 'knx_employees';

    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'initials',
        'kind',
        'knx_role',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): KnxEmployeeFactory
    {
        return KnxEmployeeFactory::new();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The account this person logs in with, when they have one. Nullable on
     * purpose: treating a technician from the team sheet as a person first, and
     * as an account holder second, is what lets the office plan work for someone
     * who has never signed in.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOffice(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_OFFICE);
    }

    public function scopeField(Builder $query): Builder
    {
        return $query->where('kind', self::KIND_FIELD);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * The short display name the contract uses everywhere: first initial of the
     * given name, then the rest of it verbatim — "Jan Van Dyck" → "J. Van Dyck",
     * "Lien Smet" → "L. Smet", "P. Aerts" → "P. Aerts". Keeping the middle words
     * matters for the Flemish names this is for ("Jan van den Broeck" must stay
     * "J. van den Broeck", not "J. Broeck").
     */
    public function shortName(): string
    {
        $parts = preg_split('/\s+/u', trim($this->name), 2) ?: [];

        $initial = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1));
        $rest = $parts[1] ?? '';

        return $rest === '' ? $initial.'.' : $initial.'. '.$rest;
    }

    /**
     * True when this person may actually open the office app.
     *
     * @see \Database\Seeders\RolesAndPermissionsSeeder for the app roles.
     */
    public function hasKantoorAccess(): bool
    {
        return $this->isOffice() && $this->active && $this->user_id !== null;
    }

    public function isOffice(): bool
    {
        return $this->kind === self::KIND_OFFICE;
    }

    public function isField(): bool
    {
        return $this->kind === self::KIND_FIELD;
    }

    /**
     * Default initials for a full name, taken from the office app's own data:
     * Jan Van Dyck → JV, Mira Claes → MC, Stijn Wouters → SW, Tom Janssens → TJ,
     * Kobe Peeters → KP, Lien Smet → LS. That is the first letter of the first
     * TWO words — not of the first and last, which would give "JD" for
     * "Jan Van Dyck" and "JW" for "Jan van den Wouwer".
     *
     * Only a default: `initials` is a stored column, so whoever creates a person
     * can override it, and this never overwrites a value already set.
     */
    public static function initialsFromName(string $name): string
    {
        $words = array_values(array_filter(
            preg_split('/\s+/u', trim($name)) ?: [],
            static fn (string $word): bool => $word !== '',
        ));

        return implode('', array_map(
            static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice($words, 0, 2),
        ));
    }
}
