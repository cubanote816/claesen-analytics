<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Knx\Database\Factories\KnxIntentFactory;
use Modules\Knx\Models\Concerns\BelongsToKnxTenant;

/**
 * Qué debe hacer un elemento (KNX-55): la intención que escribe el jefe de proyecto.
 *
 * Una fila es una **versión**. El mismo `target_key` (la identidad estable dentro del
 * proyecto) puede tener varias filas; exactamente una lleva `is_current`. Cambiar el
 * texto crea una fila nueva y marca la anterior como histórica, nunca la reescribe: el
 * texto de una intención es lo que se leyó en obra, y reescribirlo borraría la
 * evidencia de lo que el técnico vio ese día.
 */
class KnxIntent extends Model
{
    use BelongsToKnxTenant;
    use HasFactory;

    /** Un módulo del cuadro, un canal concreto o un aparato. */
    public const TARGET_MODULE = 'module';

    public const TARGET_CHANNEL = 'channel';

    public const TARGET_DEVICE = 'device';

    public const TARGET_TYPES = [self::TARGET_MODULE, self::TARGET_CHANNEL, self::TARGET_DEVICE];

    protected $table = 'knx_intents';

    protected $fillable = [
        'organization_id',
        'project_id',
        'target_type',
        'target_key',
        'board_id',
        'module_slot',
        'channel',
        'device_id',
        'room_id',
        'function_id',
        'text',
        'version',
        'is_current',
        'author_employee_id',
    ];

    protected static function newFactory(): KnxIntentFactory
    {
        return KnxIntentFactory::new();
    }

    protected function casts(): array
    {
        return ['is_current' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(KnxProject::class, 'project_id');
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(KnxBoard::class, 'board_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(KnxDevice::class, 'device_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(KnxProjectRoom::class, 'room_id');
    }

    public function function(): BelongsTo
    {
        return $this->belongsTo(KnxFunctionSpec::class, 'function_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'author_employee_id');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * La identidad externa del cuadro, la misma cadena que un marcador de plano lleva
     * en `boardId` (slug del worklist, o el código corto si el cuadro no tiene slug).
     */
    public function boardExternalId(): ?string
    {
        if ($this->board === null) {
            return null;
        }

        return $this->board->slug ?? $this->board->code;
    }
}
