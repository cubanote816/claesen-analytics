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

    public const GEOMETRY_CONTOUR = 'contour';

    public const GEOMETRY_AREA = 'area';

    public const GEOMETRY_MANUAL = 'manual';

    public const GEOMETRIES = [self::GEOMETRY_CONTOUR, self::GEOMETRY_AREA, self::GEOMETRY_MANUAL];

    /** What each geometry may say about itself; `null` (no note) is always allowed. */
    public const NOTES_BY_GEOMETRY = [
        self::GEOMETRY_CONTOUR => ['area-mismatch'],
        self::GEOMETRY_AREA => ['open', 'shared', 'in-barrier', 'too-small', 'no-vector'],
        self::GEOMETRY_MANUAL => [],
    ];

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
        'geometry',
        'holes',
        'geometry_note',
        'processing_version',
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
            'holes' => 'array',
            'area_m2' => 'decimal:2',
        ];
    }

    /**
     * The geometry a row saved before KNX-48 (or a PUT that omits it) stands for: the
     * only generator that wrote `source: pdf` produced rectangles from the label's m²,
     * never contours, so anything but a hand-drawn room is an `area` approximation.
     */
    public static function defaultGeometry(string $source): string
    {
        return $source === self::SOURCE_MANUAL ? self::GEOMETRY_MANUAL : self::GEOMETRY_AREA;
    }

    /** The stored geometry, or the legacy default for a row that has none. */
    public function effectiveGeometry(): string
    {
        return $this->geometry ?? self::defaultGeometry((string) $this->source);
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
