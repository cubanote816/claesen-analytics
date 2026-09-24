<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * CLA-602 (Fase 1 de la app KNX de Electro Bertels): punto de entrada desde
 * el menú del panel Bertels. Deliberadamente fuera del shell de Filament
 * (vista standalone con la identidad de electrobertels.md) — "debe lucir
 * como una app nueva", no como un recurso más del panel. Sin modelo de
 * datos todavía: placeholder hasta que se planifique el dominio real
 * (expedientes de instalación, dispositivos, conflictos de direcciones de
 * grupo KNX) — decisión explícita del usuario, ver CLA-602.
 *
 * Mismo control de acceso que el panel Bertels (User::canAccessPanel(),
 * ADR D10: hoy solo super_admin, porque no existe ningún usuario real de
 * Bertels todavía). Reutiliza ese método en vez de duplicar la regla, para
 * que ambos sigan la misma fuente de verdad si cambia.
 */
class KnxLandingController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->canAccessPanel(Filament::getPanel('bertels')), 403);

        return view('core::knx.landing');
    }
}
