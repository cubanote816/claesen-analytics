<?php

declare(strict_types=1);

namespace Modules\FieldOps\Enums;

enum MaintenanceRequestStatus: string
{
    case RECEIVED = 'received';
    case IN_REVIEW = 'in_review';
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';
    case REOPENED = 'reopened';
    case CLOSED = 'closed';
    case REJECTED = 'rejected';
    case DUPLICATE = 'duplicate';
    case CANCELLED = 'cancelled';
}
