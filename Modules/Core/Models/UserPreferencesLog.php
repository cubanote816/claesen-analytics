<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserPreferencesLog extends Model
{
    public $timestamps = false;

    protected $table = 'user_preferences_logs';

    protected $fillable = [
        'user_id',
        'old_preferences',
        'new_preferences',
        'changed_fields',
        'changed_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_preferences' => 'array',
            'new_preferences' => 'array',
            'changed_fields'  => 'array',
            'changed_at'      => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
