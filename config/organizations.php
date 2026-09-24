<?php

use Modules\Cafca\Models\Employee;
use Modules\Cafca\Models\EstimateItem;
use Modules\Cafca\Models\FollowupCost;
use Modules\Cafca\Models\Invoice;
use Modules\Cafca\Models\Labor;
use Modules\Cafca\Models\LegacyEmployee;
use Modules\Cafca\Models\LegacyMaterial;
use Modules\Cafca\Models\Project;
use Modules\Cafca\Models\ProjectEstimate;
use Modules\FieldOps\Models\AccessType;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\ElectricalBoardType;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenancePlan;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceRequest;
use Modules\FieldOps\Models\FoMaintenanceRequestAlert;
use Modules\FieldOps\Models\FoMaintenanceRequestMessage;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\FoMaintenanceWorkOrderEvent;
use Modules\FieldOps\Models\GeocodingCache;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\LuminaireFrameType;
use Modules\FieldOps\Models\LuminairePosition;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;
use Modules\FieldOps\Models\SafetyType;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\StructureType;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Models\TerrainType;
use Modules\Intelligence\Models\BiConfig;
use Modules\Intelligence\Models\BillingAlert;
use Modules\Intelligence\Models\MirrorSyncRun;
use Modules\Intelligence\Models\OfferSimulation;
use Modules\Intelligence\Models\PriceIndex;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\ContactPreference;
use Modules\Mailing\Models\DeliverabilityAlert;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Models\MessageEvent;
use Modules\Mailing\Models\SuppressionEntry;
use Modules\Mailing\Models\TrackedLink;
use Modules\Performance\Models\EmployeeInsight;
use Modules\Performance\Models\Mirror\MirrorCost;
use Modules\Performance\Models\Mirror\MirrorEmployee;
use Modules\Performance\Models\Mirror\MirrorEstimateCalc;
use Modules\Performance\Models\Mirror\MirrorEstimateItem;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Modules\Performance\Models\Mirror\MirrorLabor;
use Modules\Performance\Models\Mirror\MirrorLaborType;
use Modules\Performance\Models\Mirror\MirrorMaterial;
use Modules\Performance\Models\Mirror\MirrorProject;
use Modules\Performance\Models\Mirror\MirrorProjectLink;
use Modules\Performance\Models\Mirror\MirrorProjectResult;
use Modules\Performance\Models\Mirror\MirrorRelation;
use Modules\Performance\Models\Mirror\MirrorRelationDelivery;
use Modules\Performance\Models\Mirror\MirrorWorkdoc;
use Modules\Performance\Models\ProjectInsight;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Modules\Prospects\Models\SyncHistory;
use Modules\Safety\Models\Answer;
use Modules\Safety\Models\Checklist;
use Modules\Safety\Models\Inspection;
use Modules\Safety\Models\Question;
use Modules\Safety\Models\SafetyAdoptionDailyRollup;
use Modules\Safety\Models\SafetyAdoptionEvent;
use Modules\Safety\Models\SafetyEnabledUserSnapshot;

/*
|--------------------------------------------------------------------------
| Multi-organization program configuration
|--------------------------------------------------------------------------
|
| docs/ai/adr-multi-organization.md is the source of truth for every
| decision referenced below. This file is scaffolding for phases P3-P5;
| as of phase P1 nothing in the application reads it yet.
|
| `enforce` (ADR D4): with the flag off, the site-scoped global scope that
| phase P3 adds to shared-domain models stays inert — it neither filters
| nor throws. Only when this flag is explicitly turned on (phase P5) does
| it fail closed with MissingOrganizationContext when no site is resolved.
| Never flip this to true in production ahead of phase P5 being fully
| implemented and verified end to end.
|
| `owned_modules` (ADR D9): modules whose routes, resources, jobs and
| scheduled commands belong exclusively to one organization and are
| registered without any row-level organization_id/site_id column. Each
| module's `auth:sanctum` API routes carry the `organization:{slug}`
| middleware (Modules\Core\Http\Middleware\RequireOrganization) naming the
| same slug listed here — that's the actual enforcement point, this array
| is documentation/registry, nothing reads it back at runtime yet.
|
| Deliberately excluded: Core (the auth/switch-panel gateway itself — could
| never be organization-gated without breaking login), Website (`site_id`
| already scopes it, by design meant to serve Bertels too in F3), Analytics
| (`POST /api/v1/events` is intentionally public/anonymous, no authenticated
| user to compare against).
|
| Mailing is a deliberate partial inclusion (ADR D11): only the `mailings`
| campaign-management resource is gated here — its transactional transport
| (MicrosoftGraphTransport, used internally by ConsultationService, no HTTP
| route of its own) is the one thing Bertels will share, so it's never
| behind this middleware.
|
| `owned_models` (ADR D5): every concrete Eloquent model behind the 8
| modules in `owned_modules`, mapped to that module's key — the registry
| Gate::before (app/Providers/AppServiceProvider.php) consults to decide
| whether a super_admin's own organization matches a given ability's
| subject. Deliberately an ALLOWLIST, not a blocklist: a model that is
| NOT in this array is completely unaffected by phase P5c's Gate::before
| change and keeps super_admin's unconditional bypass exactly as before
| — cataloguing every model in the whole application (Core, Website,
| Safety Filament resources' auxiliary types, anything) before it's safe
| to flip the flag was judged too large and too risky to get exhaustively
| right in one pass. Grow this array deliberately as new authorization
| checks need real per-organization enforcement; never rely on it being
| complete. Employee has no entries — the module has no Eloquent models
| of its own, it reads Cafca's directly (already covered below).
| Modules\Cafca\Models\CafcaModel is abstract, never a Gate subject.
|
| `platform_abilities` (ADR D5): the only Gate abilities allowed to bypass
| the organization boundary when no Eloquent model or class-string is
| involved. Starts empty on purpose — every authorization check in this
| codebase today passes a model or a class-string, so nothing needs to be
| listed yet. Never add an ability here just to work around a check that
| is missing its model argument; fix the check instead.
|
*/

return [

    'enforce' => env('ORGANIZATIONS_ENFORCE', false),

    // CLA-598: which Site each Filament panel manages. A panel manages exactly one
    // site (ADR D1); the site is decided by the panel, never by request data.
    'panel_sites' => [
        'admin' => 'claesen-verlichting',
        'bertels' => 'electro-bertels',
    ],

    'owned_modules' => [
        'claesen' => [
            'fieldops',
            'safety',
            'employees',
            'mailing',
            'intelligence',
            'performance',
            'prospects',
            'cafca',
        ],
    ],

    'owned_models' => array_merge(
        array_fill_keys([
            AccessType::class,
            Complex::class,
            ElectricalBoard::class,
            ElectricalBoardType::class,
            FoClient::class,
            FoMaintenancePlan::class,
            FoMaintenanceRecord::class,
            FoMaintenanceRequest::class,
            FoMaintenanceRequestAlert::class,
            FoMaintenanceRequestMessage::class,
            FoMaintenanceType::class,
            FoMaintenanceWorkOrder::class,
            FoMaintenanceWorkOrderEvent::class,
            GeocodingCache::class,
            Luminaire::class,
            LuminaireFrame::class,
            LuminaireFrameType::class,
            LuminairePosition::class,
            LuminaireSubgroup::class,
            LuminaireType::class,
            SafetyType::class,
            Structure::class,
            StructureType::class,
            Terrain::class,
            TerrainType::class,
        ], 'fieldops'),
        array_fill_keys([
            Answer::class,
            Checklist::class,
            Inspection::class,
            Question::class,
            SafetyAdoptionDailyRollup::class,
            SafetyAdoptionEvent::class,
            SafetyEnabledUserSnapshot::class,
        ], 'safety'),
        array_fill_keys([
            Campaign::class,
            CampaignMessage::class,
            ContactPreference::class,
            DeliverabilityAlert::class,
            EmailTemplate::class,
            MessageEvent::class,
            SuppressionEntry::class,
            TrackedLink::class,
        ], 'mailing'),
        array_fill_keys([
            BiConfig::class,
            BillingAlert::class,
            MirrorSyncRun::class,
            OfferSimulation::class,
            PriceIndex::class,
        ], 'intelligence'),
        array_fill_keys([
            EmployeeInsight::class,
            ProjectInsight::class,
            MirrorCost::class,
            MirrorEmployee::class,
            MirrorEstimateCalc::class,
            MirrorEstimateItem::class,
            MirrorInvoice::class,
            MirrorLabor::class,
            MirrorLaborType::class,
            MirrorMaterial::class,
            MirrorProject::class,
            MirrorProjectLink::class,
            MirrorProjectResult::class,
            MirrorRelation::class,
            MirrorRelationDelivery::class,
            MirrorWorkdoc::class,
        ], 'performance'),
        array_fill_keys([
            Prospect::class,
            ProspectLocation::class,
            Region::class,
            SyncHistory::class,
        ], 'prospects'),
        array_fill_keys([
            Employee::class,
            EstimateItem::class,
            FollowupCost::class,
            Invoice::class,
            Labor::class,
            LegacyEmployee::class,
            LegacyMaterial::class,
            Project::class,
            ProjectEstimate::class,
        ], 'cafca'),
    ),

    'platform_abilities' => [],

];
