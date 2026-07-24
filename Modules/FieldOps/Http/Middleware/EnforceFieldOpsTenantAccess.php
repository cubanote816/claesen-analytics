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

    public function __construct(private readonly FieldOpsTenantService $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $this->tenants->isClientUser($user)) {
            return $next($request);
        }

        abort_unless($request->isMethodSafe() || $this->isAllowedClientMutation($request), 403);
        abort_if($request->is('api/v1/fieldops/maintenance-work-orders*'), 403);
        abort_if($request->is('api/v1/fieldops/maintenance-records/stats/*'), 403);
        abort_if($request->is('api/v1/fieldops/maintenance-records/client-reported/*'), 403);

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Media) {
                $owner = $parameter->model;
                abort_unless($owner instanceof Model && $this->tenants->canView($user, $owner), 403);

                continue;
            }

            if ($parameter instanceof Model && in_array($parameter::class, self::TENANT_MODELS, true)) {
                Gate::authorize('view', $parameter);
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
            || $request->is('api/v1/fieldops/clients/*/contacts/invitations');
    }
}
