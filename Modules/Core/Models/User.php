<?php

namespace Modules\Core\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Cafca\Models\Employee;

class User extends Authenticatable implements FilamentUser
{
    use \Laravel\Sanctum\HasApiTokens, HasFactory, Notifiable, \Spatie\Permission\Traits\HasRoles;

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
            'email_verified_at'          => 'datetime',
            'password'                   => 'hashed',
            'password_set_at'            => 'datetime',
            'activation_code_expires_at' => 'datetime',
            'last_active_at'             => 'datetime',
            'is_active'                  => 'boolean',
        ];
    }

    // Cross-connection relation: Employee is in MySQL mirror (same DB).
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // Single source of truth for "account fully activated".
    public function hasCompletedPasswordSetup(): bool
    {
        return $this->password !== null && $this->password_set_at !== null;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function isOnline(): bool
    {
        return \Illuminate\Support\Facades\Cache::has('user-is-online-' . $this->id);
    }
}
