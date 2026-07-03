<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth;

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class NoPanelAccessController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasPanelAccess()) {
            return redirect()->intended(Filament::getUrl());
        }

        return view('core::auth.no-access');
    }
}
