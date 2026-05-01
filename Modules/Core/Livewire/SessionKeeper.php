<?php

namespace Modules\Core\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class SessionKeeper extends Component
{
    public int $lifetime;
    public int $warningThreshold = 5; // 5 minutes before expiry

    public function mount(?int $lifetime = null, ?int $warningThreshold = null)
    {
        // Now working in SECONDS to avoid float-to-int conversion issues
        $this->lifetime = $lifetime ?? (config('session.lifetime', 120) * 60);
        $this->warningThreshold = $warningThreshold ?? (5 * 60);
    }

    /**
     * This method is called from JS via Livewire.entangle or direct call
     * but we'll use a standard route for the heartbeat to be more efficient.
     */
    public function logout()
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->to(route('filament.admin.auth.login', [], false));
    }

    public function render()
    {
        return view('core::livewire.session-keeper');
    }
}
