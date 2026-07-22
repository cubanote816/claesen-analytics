<?php

declare(strict_types=1);

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Core\Models\User;

class FoMaintenanceRequestMessage extends Model
{
    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_INTERNAL = 'internal';

    public const TYPE_MESSAGE = 'message';

    public const TYPE_STATUS = 'status';

    protected $table = 'fo_maintenance_request_messages';

    protected $fillable = [
        'maintenance_request_id',
        'user_id',
        'visibility',
        'type',
        'body',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Maintenance request messages are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Maintenance request messages are append-only.'));
    }

    public function maintenanceRequest()
    {
        return $this->belongsTo(FoMaintenanceRequest::class, 'maintenance_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
