<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * F1/P4+P6 of the multi-organization program (CLA-460 cont.) —
 * docs/ai/adr-multi-organization.md.
 *
 * The "selector auditado de super_admin" the ADR's P6 exit criterion names:
 * a single explicit action that switches between the Claesen (admin) and
 * Bertels panels, logging every switch. Deliberately outside any Filament
 * panel's own route group (same convention as auth.no-access/setup-password)
 * so panel middleware (EnsurePanelAccess/EnsurePasswordIsSet) never
 * interferes — this route's only gate is the super_admin check below.
 *
 * No Filament\Facades\Filament::getCurrentPanel() here — this route isn't
 * inside a panel's route group, so SetUpPanel middleware never runs and it
 * would resolve to null. The "from" panel is instead supplied explicitly by
 * whichever NavigationItem rendered the link (it already knows which panel
 * it lives in), not inferred.
 */
class SwitchPanelController extends Controller
{
    private const PANELS = ['admin', 'bertels'];

    public function __invoke(Request $request, string $panel): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);
        abort_unless(in_array($panel, self::PANELS, true), 404);

        $from = $request->query('from');
        $from = in_array($from, self::PANELS, true) ? $from : 'unknown';

        // No activity_log.organization_id column exists yet (ADR D3 plans one,
        // not built — no Bertels organizations/sites row exists either, per
        // D10's "regla de hierro"). Logging the panel identifiers in
        // properties is the complete, correct record until that column lands.
        activity('organization_context')
            ->causedBy($request->user())
            ->withProperties(['from_panel' => $from, 'to_panel' => $panel])
            ->event('panel_switched')
            ->log("Switched context to the {$panel} panel");

        return redirect($panel === 'admin' ? '/' : "/{$panel}");
    }
}
