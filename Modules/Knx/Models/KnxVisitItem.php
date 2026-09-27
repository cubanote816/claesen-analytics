<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Database\Factories\KnxVisitItemFactory;

/**
 * One line of a closure: a carried-over item, a reservation, a verified function or
 * a document handed over (V11.e, CLA-609).
 *
 * The label is free text because that is what the field app sends. It carries the
 * order it was written in, because a list read back in another order is a different
 * list to the person who wrote it.
 */
class KnxVisitItem extends Model
{
    use HasFactory;

    protected $table = 'knx_visit_items';

    protected $fillable = [
        'visit_id',
        'kind',
        'label',
        'position',
    ];

    protected static function newFactory(): KnxVisitItemFactory
    {
        return KnxVisitItemFactory::new();
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(KnxVisit::class, 'visit_id');
    }
}
