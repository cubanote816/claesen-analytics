<?php

namespace Modules\Performance\Models\Mirror;

use Illuminate\Database\Eloquent\Model;

class MirrorProject extends Model
{
    protected $table = 'intelligence_mirror_projects';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'descr',
        'relation_id',
        'category',
        'zipcode',
        'city',
        'project_address_text',
        'fl_active',
        'contract_price',
        'type',
        'state',
        'date_start',
        'date_end',
        'last_modified_at',
    ];

    protected $casts = [
        'fl_active'      => 'boolean',
        'contract_price' => 'decimal:2',
        'date_start'     => 'date',
        'date_end'       => 'date',
        'last_modified_at' => 'datetime',
    ];

    private static array $typeLabels = [
        0 => 'Industrie',
        1 => 'Industrie',
        2 => 'Openbare Verlichting',
        3 => 'Openbare Verlichting',
        4 => 'Sportverlichting',
        5 => 'Sportverlichting',
        6 => 'Masten',
        7 => 'Industrie',
        8 => 'Algemeen',
    ];

    // Source: docs/vparr/docs/reporte_db_estados.md (CLAESENSYSTEM code_type=50)
    private static array $stateLabels = [
        0  => 'Actief',
        1  => 'Bestelbon te ontvangen',
        3  => 'Eindfase',
        6  => 'Afgesloten (legacy)',
        9  => 'Materialen in goedkeuring',
        10 => 'Materialen in bestelling',
        11 => 'Werken in uitvoering',
        12 => 'Keuringen uit te voeren',
        13 => 'Afrekening nakijken',
        14 => 'Opmerkingen aan te passen',
        15 => 'Opgeleverd',
        16 => 'On Hold',
        17 => 'Verlichting te richten',
        18 => 'Archief',
        19 => 'Geannuleerd',
        20 => 'As Built af te leveren',
        21 => 'Lopende Huur / LaaS',
    ];

    /**
     * CLA-688 — the human project name.
     *
     * In CAFCA `name` holds the CLIENT name in ~95% of projects; the real
     * project name is `descr` (CLA-173). Never present `name` as the project
     * name when `descr` is filled. Falls back to `name`, then to the id, for
     * projects synced before `descr` existed. Show the client from the mirror
     * relation (`relation_id`), never from `name`.
     */
    public function getDisplayNameAttribute(): string
    {
        foreach ([$this->descr, $this->name] as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return trim((string) $this->id);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::$typeLabels[$this->type] ?? "Onbekend ({$this->type})";
    }

    public function getStateLabelAttribute(): string
    {
        return self::$stateLabels[$this->state] ?? "Onbekend ({$this->state})";
    }

    public function getTypeColorAttribute(): string
    {
        return match ($this->type) {
            2, 3    => 'blue',
            4, 5    => 'green',
            6       => 'purple',
            default => 'gray',
        };
    }

    public function getStateColorAttribute(): string
    {
        return match ((int) $this->state) {
            0, 1, 9, 10, 11, 17 => 'green',
            16                  => 'orange',
            19                  => 'red',
            15, 18              => 'gray',
            default             => 'blue',
        };
    }
}
