<?php

declare(strict_types=1);

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * One room drawn on one page of one plan revision (KNX-45).
 *
 * `polygon` is a list of `[nx, ny]` points as fractions of the page; `label_at` is where
 * the room's label sits. Both are opaque: the server does not parse the PDF and has no
 * opinion about the geometry. `state` carries provenance — `proposed` (extracted or
 * carried over) vs `confirmed` (a person drew or accepted it).
 */
class KnxPlanRoom extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_PDF = 'pdf';

    public const SOURCE_DWG = 'dwg';

    public const SOURCES = [self::SOURCE_MANUAL, self::SOURCE_PDF, self::SOURCE_DWG];

    public const STATE_PROPOSED = 'proposed';

    public const STATE_CONFIRMED = 'confirmed';

    public const STATES = [self::STATE_PROPOSED, self::STATE_CONFIRMED];

    protected $table = 'knx_plan_rooms';

    protected $fillable = [
        'organization_id',
        'project_id',
        'document_id',
        'room_id',
        'page',
        'polygon',
        'label_at',
        'area_m2',
        'source',
        'state',
        'placed_by_employee_id',
        'page_width_pt',
        'page_height_pt',
    ];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'polygon' => 'array',
            'label_at' => 'array',
            'area_m2' => 'decimal:2',
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

    public function room(): BelongsTo
    {
        return $this->belongsTo(KnxProjectRoom::class, 'room_id');
    }
}
