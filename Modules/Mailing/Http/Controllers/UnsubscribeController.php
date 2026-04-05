<?php

namespace Modules\Mailing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Prospects\Models\Prospect;
use Illuminate\Support\Facades\App;

class UnsubscribeController extends Controller
{
    /**
     * Show the unsubscribe confirmation page.
     */
    public function show(Prospect $prospect, string $token)
    {
        $this->verifyToken($prospect, $token);

        // Set locale based on prospect language
        App::setLocale($prospect->language ?? 'nl');

        return view('mailing::unsubscribe', [
            'prospect' => $prospect,
            'token' => $token,
            'completed' => false,
        ]);
    }

    /**
     * Process the unsubscribe request.
     */
    public function store(Prospect $prospect, string $token)
    {
        $this->verifyToken($prospect, $token);

        // Set locale based on prospect language
        App::setLocale($prospect->language ?? 'nl');

        $prospect->update([
            'unsubscribed_at' => now(),
        ]);

        return view('mailing::unsubscribe', [
            'prospect' => $prospect,
            'token' => $token,
            'completed' => true,
        ]);
    }

    /**
     * Verify the unsubscribe token.
     */
    protected function verifyToken(Prospect $prospect, string $token): void
    {
        if ($prospect->getUnsubscribeToken() !== $token) {
            abort(403, 'Invalid unsubscribe token.');
        }
    }
}
