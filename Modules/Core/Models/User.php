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
    // External client contacts use a dedicated application (Claesen-Client).
    // CLA-581: technician gets panel access too, but only to Modules/FieldOps/Filament/Pages/MyWorkOrders —
    // every other FieldOps resource still gates on hasAnyRole(['super_admin','admin']) and stays hidden.
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
            'technician',
        ]);
    }

    /**
     * ¿Puede esta persona abrir la entrada a la app KNX de oficina? (CLA-602)
     *
     * Antes era la misma pregunta que `canAccessPanel('bertels')`, y lo era de verdad
     * mientras a ese panel solo entrara `super_admin`. Desde CLA-611 el panel admite
     * además a los usuarios de la organización dueña del sitio —el cliente entra ahí a
     * aprobar sus páginas—, así que las dos preguntas se separaron: entrar al panel ya no
     * significa ser de la casa, y la app de oficina es de personal de Electro Bertels.
     *
     * Existe como método con nombre para que el menú y la ruta pregunten lo mismo: esa
     * era exactamente la razón por la que KnxLandingController reutilizaba
     * `canAccessPanel` en lugar de escribir su propia regla.
     *
     * Hoy es `super_admin` porque no existe ningún usuario real de Bertels (ADR D10). El
     * día que existan, esta es la regla a abrir —y el sitio donde decidir si alcanza con
     * tener ficha de empleado KNX de oficina—, sin tocar el acceso al panel.
     */
    public function canOpenKnxOffice(): bool
    {
        return $this->is_active && $this->hasRole('super_admin');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // CLA-611 (2026-09-29, decisión del usuario: "abrir el panel a los usuarios del
        // cliente"). Hasta ahora el panel bertels era sólo para super_admin porque no
        // existía ningún usuario real de Bertels (ADR D10). Ahora entra también la
        // gente **de la organización dueña del sitio del panel** — y nadie más.
        //
        // La pertenencia se deriva del sitio del panel (config('organizations.panel_sites'))
        // y no de un rol: el rol es global, la organización no. Fail-closed: sin sitio,
        // sin organización en el sitio, o inactivo → fuera.
        if ($panel->getId() === 'bertels') {
            if (! $this->is_active) {
                return false;
            }

            if ($this->hasRole('super_admin')) {
                return true;
            }

            $siteOrganizationId = Site::forPanel($panel->getId())?->organization_id;

            return $siteOrganizationId !== null && $this->organization_id === $siteOrganizationId;
        }

        // CLA-611: el panel admin (y cualquier otro) admite **sólo** a la gente de Claesen
        // cuando su organización está definida. Esto es incondicional a propósito, y se
        // desvía del diseño D4, que lo dejaba detrás de config('organizations.enforce'):
        // la admisión a un panel es una frontera de acceso, no una regla de negocio que
        // pueda quedarse apagada — y con el flag apagado (el estado de hoy) la única
        // barrera entre un usuario de Bertels y los datos de Claesen era que todavía no
        // existía ninguno, que es exactamente lo que el ADR D10 prohíbe.
        //
        // `organization_id` nulo sigue permitido: los usuarios sembrados
        // (DatabaseSeeder) y los anteriores a P2 lo tienen nulo, y el programa trae
        // `core:backfill-user-organizations` para asignarlos a Claesen. Un usuario de
        // Bertels nunca es nulo: su alta define la organización primero.
        if ($this->organization_id !== null && $this->organization_id !== Organization::claesenId()) {
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
