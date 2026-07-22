<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Factories\FoClientFactory;

class FoClient extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fo_clients';

    protected $fillable = [
        'relation_id', 'name', 'city', 'street', 'phone', 'email', 'language',
    ];

    protected static function newFactory(): FoClientFactory
    {
        return FoClientFactory::new();
    }

    public function complexes()
    {
        return $this->hasMany(Complex::class, 'client_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'fo_client_user', 'fo_client_id', 'user_id')
            ->withPivot(['is_active', 'can_view', 'can_report', 'can_manage_contacts'])
            ->withTimestamps();
    }
}
