<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

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
 * Control de acceso: `User::canOpenKnxOffice()`, no `canAccessPanel('bertels')`.
 * Reutilizaba ese método «para que ambos sigan la misma fuente de verdad si cambia», y
 * cambió: desde CLA-611 el panel bertels también admite a los usuarios de la
 * organización del cliente (entran a aprobar sus páginas), de modo que colgar esta
 * entrada de aquella comprobación le abría al cliente una app de personal. La fuente de
 * verdad sigue siendo única; simplemente ahora nombra lo que de verdad se pregunta.
 */
class KnxLandingController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->canOpenKnxOffice(), 403);

        return view('core::knx.landing');
    }
}
