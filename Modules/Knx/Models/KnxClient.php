<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;
use Modules\Knx\Database\Factories\KnxClientFactory;

/**
 * A client of the installation business (`Client` in the contract).
 */
class KnxClient extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    protected $table = 'knx_clients';

    protected $fillable = [
        'organization_id',
        'name',
        'city',
        'contact',
        'phone',
        'email',
        'address',
        'vat',
    ];

    protected static function newFactory(): KnxClientFactory
    {
        return KnxClientFactory::new();
    }

    public function projects(): HasMany
    {
        return $this->hasMany(KnxProject::class, 'client_id');
    }
}
