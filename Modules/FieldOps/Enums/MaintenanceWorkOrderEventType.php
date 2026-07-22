<?php

declare(strict_types=1);

namespace Modules\FieldOps\Enums;

enum MaintenanceWorkOrderEventType: string
{
    case CREATED = 'created';
    case ASSIGNED = 'assigned';
    case REASSIGNED = 'reassigned';
    case UNASSIGNED = 'unassigned';
    case STARTED = 'started';
    case SUBMITTED = 'submitted';
    case RETURNED = 'returned';
    case VALIDATED = 'validated';
    case OVERRIDDEN = 'overridden';
    case CANCELLED = 'cancelled';
}
