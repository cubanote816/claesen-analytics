<?php

namespace Modules\Prospects\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\User;
use Modules\Prospects\Database\Factories\ContactLogFactory;
use Modules\Prospects\Enums\ContactDirection;
use Modules\Prospects\Enums\ContactMethod;

/**
 * CLA-711: one manual "we talked to this prospect" entry, logged by a system user.
 */
class ContactLog extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return ContactLogFactory::new();
    }

    protected $table = 'prospects_contact_logs';

    protected $fillable = [
        'prospect_id',
        'user_id',
        'contacted_at',
        'direction',
        'method',
        'note',
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
        'direction' => ContactDirection::class,
        'method' => ContactMethod::class,
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
