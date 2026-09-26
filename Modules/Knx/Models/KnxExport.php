<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;
use Modules\Knx\Database\Factories\KnxExportFactory;

/**
 * A generated report. `POST /reports` answers 202 and queues the work, so
 * `status` is what tells the office whether the file is ready.
 */
class KnxExport extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    public const TYPE_DOSSIER = 'dossier';

    public const TYPE_ETS = 'ets';

    public const TYPE_DELIVERY = 'delivery';

    /** Declared as a valid type by §4.9, but with no data source in this domain yet. */
    public const TYPE_HOURS = 'hours';

    public const TYPES = [self::TYPE_DOSSIER, self::TYPE_ETS, self::TYPE_DELIVERY, self::TYPE_HOURS];

    public const STATUS_QUEUED = 'queued';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $table = 'knx_exports';

    protected $fillable = ['organization_id', 'project_id', 'type', 'status', 'path', 'created_by_employee_id'];

    protected static function newFactory(): KnxExportFactory
    {
        return KnxExportFactory::new();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'created_by_employee_id');
    }
}
