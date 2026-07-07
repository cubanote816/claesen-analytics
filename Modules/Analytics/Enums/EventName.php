<?php

declare(strict_types=1);

namespace Modules\Analytics\Enums;

/**
 * Single source of truth for valid event names across every internal app.
 *
 * The `app` column on app_events already namespaces an event to its source
 * (e.g. "maintenance_record_created" only ever comes from fieldops_sport),
 * so event names stay flat snake_case rather than dot-prefixed — no team
 * invents a name that isn't a case here, and StoreAppEventRequest rejects
 * anything else at the door (Rule::enum).
 *
 * CLA-229: transversal events are wired end-to-end (EventTracker + ingestion
 * endpoint). The per-app cases below are catalogued and ready to receive
 * traffic once each frontend is instrumented — no schema/catalog change
 * needed to start emitting them.
 */
enum EventName: string
{
    // Transversal — implemented and emittable by every app today.
    case SESSION_STARTED = 'session_started';
    case SESSION_ENDED = 'session_ended';
    case ACTION_STARTED = 'action_started';
    case ACTION_COMPLETED = 'action_completed';
    case ACTION_ABANDONED = 'action_abandoned';
    case ERROR_ENCOUNTERED = 'error_encountered';
    case FEATURE_VIEWED = 'feature_viewed';

    // Safety PWA — reserved, not yet emitted by the frontend.
    case INSPECTION_STARTED = 'inspection_started';
    case INSPECTION_COMPLETED = 'inspection_completed';
    case INSPECTION_PHOTO_UPLOAD_FAILED = 'inspection_photo_upload_failed';
    case COMPLIANCE_ALERT_VIEWED = 'compliance_alert_viewed';

    // Claesen-Sport / FieldOps — reserved, not yet emitted by the frontend.
    case STRUCTURE_VIEWED = 'structure_viewed';
    case MAINTENANCE_RECORD_CREATED = 'maintenance_record_created';
    case MAINTENANCE_FORM_ABANDONED = 'maintenance_form_abandoned';
    case MAP_MARKER_ADJUSTED = 'map_marker_adjusted';
    case CLIENT_REPORTED_ISSUE_CREATED = 'client_reported_issue_created';

    // Backoffice — reserved, not yet wired into Filament resources.
    case RESOURCE_CREATED = 'resource_created';
    case RESOURCE_UPDATED = 'resource_updated';
    case REPORT_EXPORTED = 'report_exported';
}
