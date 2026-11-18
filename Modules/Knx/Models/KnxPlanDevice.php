<?php

declare(strict_types=1);

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * A KNX device of one plan page (KNX-48, plano IA v2): BORD, pushbutton or detector, with
 * its review status. Geometry is in page fractions and `symbol_id` points to the original
 * symbol of the PDF; the server never reads the PDF.
 */
class KnxPlanDevice extends Model
{
    use BelongsToKnxTenant;

    public const TYPES = ['bord', 'pushbutton', 'detector_180', 'detector_360', 'other_knx'];

    public const STATUSES = ['proposed', 'confirmed', 'rejected'];

    protected $table = 'knx_plan_devices';

    protected $fillable = [
        'organization_id',
        'project_id',
        'document_id',
        'page',
        'document_revision',
        'provisional_id',
        'type',
        'nx',
        'ny',
        'bbox',
        'rotation_deg',
        'symbol_id',
        'label',
        'status',
        'doubts',
        'analysis_id',
        'updated_by_employee_id',
    ];

    protected function casts(): array
    {
        return [
            'page' => 'integer',
            'nx' => 'float',
            'ny' => 'float',
            'bbox' => 'array',
            'rotation_deg' => 'float',
            'doubts' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnxDocument::class, 'document_id');
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(KnxPlanAnalysis::class, 'analysis_id');
    }
}
