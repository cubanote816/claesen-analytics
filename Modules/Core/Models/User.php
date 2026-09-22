<?php

namespace Modules\Core\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\Email\Concerns\InteractsWithEmailAuthentication;
use Filament\Auth\MultiFactor\Email\Contracts\HasEmailAuthentication;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Modules\Cafca\Models\Employee;
use Modules\Core\Models\AccessEvent;
use Modules\Core\Notifications\SuperAdminGrantedNotification;
use Modules\FieldOps\Models\FoClient;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * CLA-464 (ADR D8): implements Filament 5's native MFA contracts (App/TOTP +
 * recovery codes, Email) via its own ready-made traits — the column names
 * below (app_authentication_secret, app_authentication_recovery_codes,
 * has_email_authentication) are fixed by those traits, not chosen here.
 * Enforcement (which panels/roles require it) lives in each PanelProvider's
 * ->multiFactorAuthentication() call, not in this model.
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasEmailAuthentication
{
    use HasFactory, \Laravel\Sanctum\HasApiTokens, Notifiable;

    // F2/CLA-465: aliased so assignRole()/syncRoles() can be overridden below
    // to detect a super_admin grant — Spatie Permission itself never fires a
    // domain event for this (model_has_roles is written via attach()/sync(),
    // no Eloquent events involved).
    use HasRoles {
        assignRole as private baseAssignRole;
        syncRoles as private baseSyncRoles;
    }
    use InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, InteractsWithEmailAuthentication;

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'name',
        'email',
        'is_active',
        'password',
        'password_set_at',
        'employee_id',
        'organization_id',
        'microsoft_id',
        'azure_token',
        'azure_refresh_token',
        'azure_token_expires_at',
        'language',
        'theme',
        'preferences_data',
        'last_login_at',
        'last_login_app_source',
        'last_login_channel',
        'has_email_authentication',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'activation_code_hash',
        'activation_code_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_set_at' => 'datetime',
            'activation_code_expires_at' => 'datetime',
            'last_active_at' => 'datetime',
            'is_active' => 'boolean',
            'preferences_data' => 'array',
            'last_login_at'              => 'datetime',
        ];
    }

    // Cross-connection relation: Employee is in MySQL mirror (same DB).
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // F1/P2 (docs/ai/adr-multi-organization.md): resolve-only relation — see
    // Modules\Core\Services\OrganizationContext. F1/P5d added the first real
    // consumer: scopeInOrganization() below, used to keep operational
    // notification recipients scoped to one organization.
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    // F1/P5d (ADR §6, "asíncrono y destinatarios"): every recipient query this
    // model's own role scopes feed (User::role(...)/whereHas('roles', ...))
    // selected users by role alone, globally — the P0 baseline
    // (AccessControlContractTest::operationalRecipientQueries) froze exactly
    // this gap across 7 call sites in FieldOps/Safety/Mailing. Gated by
    // config('organizations.enforce') (D4) — a no-op with the flag off (the
    // default in every environment today). Defaults to Claesen because every
    // one of those 7 notifications is a Claesen-operational context today;
    // pass an explicit id for anything that isn't.
    public function scopeInOrganization(Builder $query, ?int $organizationId = null): Builder
    {
        if (! config('organizations.enforce')) {
            return $query;
        }

        return $query->where('organization_id', $organizationId ?? Organization::claesenId());
    }

    /**
     * F2/CLA-465: "alertas por creación de super_admin" — the only two real
     * call sites (Modules\Core\Filament\Resources\Users\Pages\CreateUser::
     * handleRecordCreation() and EditUser's "changeRoles" action) both use
     * syncRoles(); assignRole() is overridden too for any future/other
     * caller. Both funnel through maybeNotifySuperAdminGranted() so the
     * detection logic lives in exactly one place.
     *
     * $isInsideRoleMutation guards against a real re-entrancy bug found
     * while testing this: Spatie's own syncRoles() body (vendor/spatie/
     * laravel-permission/src/Traits/HasRoles.php) detaches every current
     * role and THEN calls `$this->assignRole($roles)` to re-add them —
     * that inner call is dispatched dynamically on $this, so PHP resolves
     * it to THIS class's own assignRole() override below, not the trait's
     * original (aliasing a trait method never rewrites the trait's own
     * internal self-calls). Without this guard, re-syncing the SAME roles
     * a user already had would still fire a spurious "granted" alert,
     * because the inner assignRole() call would see the mid-flight
     * "just detached, has no roles yet" state as its own "before" snapshot.
     */
    private bool $isInsideRoleMutation = false;

    public function assignRole(...$roles): static
    {
        if ($this->isInsideRoleMutation) {
            return $this->baseAssignRole(...$roles);
        }

        $hadSuperAdminBefore = $this->exists && $this->fresh()->hasRole('super_admin');
        $this->isInsideRoleMutation = true;

        try {
            $result = $this->baseAssignRole(...$roles);
        } finally {
            $this->isInsideRoleMutation = false;
        }

        $this->maybeNotifySuperAdminGranted($hadSuperAdminBefore);

        return $result;
    }

    public function syncRoles(...$roles): static
    {
        $hadSuperAdminBefore = $this->exists && $this->fresh()->hasRole('super_admin');
        $this->isInsideRoleMutation = true;

        try {
            $result = $this->baseSyncRoles(...$roles);
        } finally {
            $this->isInsideRoleMutation = false;
        }

        $this->maybeNotifySuperAdminGranted($hadSuperAdminBefore);

        return $result;
    }

    private function maybeNotifySuperAdminGranted(bool $hadSuperAdminBefore): void
    {
        if ($hadSuperAdminBefore || (! $this->exists)) {
            return;
        }

        if (! $this->fresh()->hasRole('super_admin')) {
            return;
        }

        $roleIds = Role::whereIn('name', ['super_admin', 'admin'])->pluck('id');
        $recipients = static::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('id', $roleIds))
            ->where('id', '!=', $this->id)
            ->inOrganization($this->organization_id)
            ->get();

        Notification::send(
            $recipients,
            new SuperAdminGrantedNotification($this)
        );
    }

    public function fieldOpsClients(): BelongsToMany
    {
        return $this->belongsToMany(FoClient::class, 'fo_client_user', 'user_id', 'fo_client_id')
            ->withPivot(['is_active', 'can_view', 'can_report', 'can_manage_contacts'])
            ->withTimestamps();
    }

    public function accessEvents(): HasMany
    {
        return $this->hasMany(AccessEvent::class);
    }

    // Single source of truth for "account fully activated".
    // SSO users (microsoft_id set) are always considered activated — they authenticate via Azure.
    public function hasCompletedPasswordSetup(): bool
    {
        return $this->microsoft_id !== null
            || ($this->password !== null && $this->password_set_at !== null);
    }

    // Single source of truth for "can use the Filament backoffice".
    // Field workers and external client contacts use dedicated applications.
    public function hasPanelAccess(): bool
    {
        if ($this->hasRole('client')) {
            return false;
        }

        return $this->hasAnyRole([
            'super_admin',
            'admin',
            'financial_manager',
            'hr_manager',
            'viewer',
        ]);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // F1/P6 spike (CLA-549, docs/ai/adr-multi-organization.md): no real
        // Bertels user exists yet (ADR D10, "regla de hierro") — the panel is
        // reachable only by super_admin while it has no resources of its own.
        // Filament's own Authenticate middleware calls this per-panel and
        // abort_if(403)s on false (vendor/filament/filament/.../Authenticate.php),
        // so this is the intended extension point rather than a bespoke
        // middleware duplicating EnsurePanelAccess's Claesen role allowlist.
        if ($panel->getId() === 'bertels') {
            return $this->is_active && $this->hasRole('super_admin');
        }

        // F1/P5a (CLA-460 cont., docs/ai/adr-multi-organization.md): "paneles
        // y logins" is the first enforcement layer. Gated by the flag (D4) —
        // with it off (the default everywhere today) this branch never runs,
        // so nothing changes for Claesen. With it on, a user outside
        // Claesen's organization is rejected from the admin panel the same
        // way the bertels branch above already rejects non-super_admin: via
        // this method, which Filament's own Authenticate middleware already
        // calls per-panel and abort_if(403)s on false. No real non-Claesen
        // user exists yet (D10) — this can only be exercised with a fixture
        // organization in tests until P7.
        if (config('organizations.enforce') && $this->organization_id !== Organization::claesenId()) {
            return false;
        }

        // Keep Filament authentication available so EnsurePanelAccess can send
        // non-panel users to the dedicated no-access page and still allow logout.
        // CLA-363: the actual login-time rejection for client/technician lives in
        // \Modules\Core\Filament\Pages\Auth\Login::authenticate() instead of here —
        // this method is also called by Filament's Authenticate middleware on
        // every panel request (not just login), and an already-authenticated
        // session failing it gets 403'd on every route including logout (see
        // EnsurePanelAccess's comment on why that's avoided).
        return (bool) $this->is_active;
    }

    public function isOnline(): bool
    {
        return Cache::has('user-is-online-'.$this->id);
    }
}
