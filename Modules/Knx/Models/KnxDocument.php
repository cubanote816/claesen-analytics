<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;
use Modules\Knx\Database\Factories\KnxDocumentFactory;

/**
 * A plan or document of a project.
 *
 * `size_bytes` is stored raw and formatted by the API resource (`size` in the
 * contract is human text, "4,2 MB"): keeping the number is lossless and lets the
 * front format it later without a migration. `url` is never stored — the API
 * returns a signed temporary URL.
 */
class KnxDocument extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    protected $table = 'knx_documents';

    protected $fillable = [
        'organization_id',
        'project_id',
        'name',
        'kind',
        'size_bytes',
        'path',
        'revision',
        'is_current',
        'approved_by_employee_id',
        'uploaded_by_employee_id',
        'uploaded_at',
    ];

    protected static function newFactory(): KnxDocumentFactory
    {
        return KnxDocumentFactory::new();
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_current' => 'boolean',
            'uploaded_at' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'uploaded_by_employee_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'approved_by_employee_id');
    }
}
