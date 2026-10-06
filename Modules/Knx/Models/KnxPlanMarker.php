<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * One board placed on a revision of a plan (KNX-3).
 *
 * `board_id` is a string — the board's external identity, not a numeric FK — so the
 * marker points at the same id the boards endpoint returns. `nx`/`ny` are fractions
 * of the page in points, cast to float so the JSON carries a number and not the
 * decimal column's string form.
 */
class KnxPlanMarker extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    protected $table = 'knx_plan_markers';

    protected $fillable = [
        'organization_id',
        'project_id',
        'document_id',
        'board_id',
        'page',
        'page_width_pt',
        'page_height_pt',
        'nx',
        'ny',
        'placed_by_employee_id',
    ];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'page_width_pt' => 'float',
            'page_height_pt' => 'float',
            'nx' => 'float',
            'ny' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnxDocument::class, 'document_id');
    }

    public function placedBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'placed_by_employee_id');
    }
}
