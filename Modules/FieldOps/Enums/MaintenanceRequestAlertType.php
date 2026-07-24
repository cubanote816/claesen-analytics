<?php

declare(strict_types=1);

namespace Modules\FieldOps\Enums;

enum MaintenanceRequestAlertType: string
{
    case NO_FIRST_RESPONSE = 'no_first_response';
    case AWAITING_CONFIRMATION = 'awaiting_confirmation';

    public function label(): string
    {
        return match ($this) {
            self::NO_FIRST_RESPONSE => 'No first response',
            self::AWAITING_CONFIRMATION => 'Awaiting client confirmation',
        };
    }
}
