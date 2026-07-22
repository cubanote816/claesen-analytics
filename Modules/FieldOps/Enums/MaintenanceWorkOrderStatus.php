<?php

declare(strict_types=1);

namespace Modules\FieldOps\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MaintenanceWorkOrderStatus: string implements HasColor, HasLabel
{
    case PLANNED = 'planned';
    case ASSIGNED = 'assigned';
    case IN_PROGRESS = 'in_progress';
    case AWAITING_VALIDATION = 'awaiting_validation';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): ?string
    {
        return __('fieldops::resource.work_orders.status.'.$this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PLANNED => 'gray',
            self::ASSIGNED => 'info',
            self::IN_PROGRESS => 'warning',
            self::AWAITING_VALIDATION => 'primary',
            self::COMPLETED => 'success',
            self::CANCELLED => 'danger',
        };
    }
}
