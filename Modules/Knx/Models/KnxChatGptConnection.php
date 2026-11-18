<?php

declare(strict_types=1);

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * One employee's own ChatGPT connection. Only that employee's analyses may ever use it.
 * The tokens never leave the server: they are hidden from serialization and encrypted at rest.
 */
class KnxChatGptConnection extends Model
{
    use BelongsToKnxTenant;

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_NEEDS_SIGN_IN = 'needs_sign_in';

    protected $table = 'knx_chatgpt_connections';

    protected $fillable = [
        'organization_id',
        'employee_id',
        'subject',
        'email',
        'name',
        'client_id',
        'scopes',
        'access_token',
        'refresh_token',
        'access_expires_at',
        'plan_permission',
        'status',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_expires_at' => 'datetime',
            'plan_permission' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'employee_id');
    }
}
