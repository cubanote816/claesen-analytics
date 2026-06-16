<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorCost extends Model
{
    protected $table = 'intelligence_mirror_costs';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'project_id',
        'art_id',
        'descr',
        'type',
        'cost_price',
        'quantity',
        'extra_type',
        'date',
        'invoiced',
    ];

    protected $casts = [
        'cost_price' => 'float',
        'quantity'   => 'float',
        'date'       => 'date',
        'invoiced'   => 'boolean',
    ];

    /**
     * Source: CAFCASYSTEM.CAFCA.code WHERE code=3 (confirmed 2026-06-16, identical in CLAESENSYSTEM).
     *
     * price_type 0 is heavily used (10k+ lines, €16M+) but absent from ERP lookup code=3.
     * Sampled data shows free-text/legacy entries without art_id catalog reference.
     * Label "Overige (vrij)" is provisional — pending final confirmation from Bert Bertels.
     */
    private static array $priceTypeLabels = [
        0 => 'Overige (vrij)',
        1 => 'Materiaal',
        2 => 'Arbeid',
        3 => 'Materieel',
        4 => 'Onderaanneming',
        5 => 'Element',
    ];

    public static function priceTypeLabel(string|int|null $type): string
    {
        if ($type === null || $type === '') {
            return 'Onbekend';
        }

        $key = (int) $type;

        return self::$priceTypeLabels[$key] ?? "CAFCA type {$type}";
    }

    public static function priceTypeIsProvisional(string|int|null $type): bool
    {
        return $type !== null && $type !== '' && (int) $type === 0;
    }
}
