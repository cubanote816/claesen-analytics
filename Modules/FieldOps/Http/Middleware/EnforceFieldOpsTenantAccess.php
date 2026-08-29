<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\FieldOps\Models\Complex as FieldOpsComplex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceRequest;
use Modules\FieldOps\Models\FoMaintenanceWorkOrder;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Services\FieldOpsTenantService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class EnforceFieldOpsTenantAccess
{
    private const TENANT_MODELS = [
        FoClient::class,
        FieldOpsComplex::class,
        Terrain::class,
        Structure::class,
        LuminaireFrame::class,
        Luminaire::class,
        ElectricalBoard::class,
        FoMaintenanceRecord::class,
        FoMaintenanceWorkOrder::class,
        FoMaintenanceRequest::class,
    ];

    // CLA-496: only these 6 get method-aware dispatch (update/delete abilities on
    // PUT/PATCH/DELETE). Everything else in TENANT_MODELS (FoClient + the 3
    // maintenance models) keeps the single 'view' check on every method, exactly as
    // before this ticket — their write rules live in their own services, not here.
    private const INFRASTRUCTURE_MODELS = [
        FieldOpsComplex::class,
        Terrain::class,
        Structure::class,
        LuminaireFrame::class,
        Luminaire::class,
        ElectricalBoard::class,
    ];

    public function __construct(private readonly FieldOpsTenantService $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // CLA-369: this block is Client Portal-only — the work-order/stats
        // blanket blocks and the mutation whitelist are about what a client
        // may do, not about tenant scope. A scoped technician/project_manager
        // (no fieldops.view-all-clients) is neither exempt here nor subject to
        // these client-specific restrictions; they fall through to the
        // scoping enforcement below instead.
        if ($this->tenants->isClientUser($user)) {
            abort_unless($request->isMethodSafe() || $this->isAllowedClientMutation($request), 403);
            abort_if($request->is('api/v1/fieldops/maintenance-work-orders*'), 403);
            abort_if($request->is('api/v1/fieldops/maintenance-records/stats/*'), 403);
            abort_if($request->is('api/v1/fieldops/maintenance-records/client-reported/*'), 403);
        }

        // CLA-496: hasBroadAccess() must NOT short-circuit here anymore — it only
        // waives the ownership/scope check inside canView() (already handled there
        // independently, see FieldOpsTenantService::canView()). The capability check
        // below (create/update/delete permission) must still run for every role,
        // broad-access included, or a viewer/financial_manager/hr_manager with
        // fieldops.view-all-clients but no write permission would sail through.

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Media) {
                $owner = $parameter->model;
                abort_unless($owner instanceof Model && $this->tenants->canView($user, $owner), 403);

                continue;
            }

            if ($parameter instanceof Model && in_array($parameter::class, self::TENANT_MODELS, true)) {
                $ability = 'view';

                if (in_array($parameter::class, self::INFRASTRUCTURE_MODELS, true)) {
                    $ability = match ($request->method()) {
                        'PUT', 'PATCH' => 'update',
                        'DELETE' => 'delete',
                        default => 'view', // GET/HEAD/POST unchanged — POST sub-actions
                        // (vision-suggestions, replacement, etc.) keep the same 'view'
                        // baseline they had before this ticket; any additional
                        // ability on top of that is added explicitly by the ticket
                        // that owns that specific action, never here.
                    };
                }

                Gate::authorize($ability, $parameter);
            }
        }

        return $next($request);
    }

    private function isAllowedClientMutation(Request $request): bool
    {
        if (! $request->isMethod('post')) {
            return false;
        }

        return $request->is('api/v1/fieldops/maintenance-requests')
            || $request->is('api/v1/fieldops/maintenance-requests/intake/suggest')
            || $request->is('api/v1/fieldops/maintenance-requests/*/messages')
            || $request->is('api/v1/fieldops/maintenance-requests/*/attachments')
            || $request->is('api/v1/fieldops/maintenance-requests/*/confirm')
            || $request->is('api/v1/fieldops/maintenance-requests/*/reopen')
            || $request->is('api/v1/fieldops/maintenance-requests/*/cancel')
            || $request->is('api/v1/fieldops/notifications/read-all')
            || $request->is('api/v1/fieldops/notifications/*/read')
            || $request->is('api/v1/fieldops/clients/*/contacts/invitations');
    }
}
