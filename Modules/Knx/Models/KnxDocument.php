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
        'mime_type',
        'size_bytes',
        'pages',
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
            'pages' => 'integer',
            'is_current' => 'boolean',
            'uploaded_at' => 'date',
        ];
    }

    /**
     * The content types the field app can render (its `PlanMime`).
     *
     * Anything else is not a drawing the app could open, which is why the map is
     * this short instead of listing every type we might ever store.
     */
    private const DRAWING_MIME_TYPES = [
        'pdf' => 'application/pdf',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
    ];

    /**
     * The MIME type of this document, or `null` when it cannot be named.
     *
     * The column wins; otherwise it is derived from the file extension, which is
     * the only other fact the office has (it never stores a content type). `null`
     * is a real answer: the field endpoint leaves such a document out rather than
     * sending a type the app would pick the wrong renderer for.
     */
    public function resolvedMimeType(): ?string
    {
        return $this->mime_type ?? self::mimeTypeForName($this->name);
    }

    public static function mimeTypeForName(string $name): ?string
    {
        return self::DRAWING_MIME_TYPES[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? null;
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
