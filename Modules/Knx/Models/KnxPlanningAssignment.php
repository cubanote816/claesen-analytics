<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;
use Modules\Knx\Database\Factories\KnxPlanningAssignmentFactory;

/**
 * One assignment per technician per day — `PUT /planning` is idempotent on
 * (employee, date); the unique index on the table is what enforces it.
 */
class KnxPlanningAssignment extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    protected $table = 'knx_planning_assignments';

    protected $fillable = ['organization_id', 'employee_id', 'project_id', 'date'];

    protected static function newFactory(): KnxPlanningAssignmentFactory
    {
        return KnxPlanningAssignmentFactory::new();
    }

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /** The assigned technician — a `field` employee. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'employee_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }
}
