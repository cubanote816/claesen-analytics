<?php

namespace Modules\Core\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Core\Models\AccessEvent;
use Modules\Cafca\Models\Employee;
use Modules\FieldOps\Models\FoClient;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, \Laravel\Sanctum\HasApiTokens, Notifiable, \Spatie\Permission\Traits\HasRoles;

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
        return \Illuminate\Support\Facades\Cache::has('user-is-online-'.$this->id);
    }
}
