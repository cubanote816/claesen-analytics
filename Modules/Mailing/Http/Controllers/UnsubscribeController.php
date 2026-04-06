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
     * Handle unsubscription via API (from external proxy).
     */
    public function apiUnsubscribe(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('Unsubscribe API received', $request->all());

        $request->validate([
            'prospect_id' => 'required|exists:prospects_prospects,id',
            'token' => 'required|string',
        ]);

        $prospect = Prospect::find($request->prospect_id);

        if (!$prospect) {
             \Illuminate\Support\Facades\Log::warning('Unsubscribe API: Prospect not found', ['id' => $request->prospect_id]);
             return response()->json(['message' => 'Prospect not found.'], 404);
        }

        if ($prospect->getUnsubscribeToken() !== $request->token) {
            \Illuminate\Support\Facades\Log::warning('Unsubscribe API: Invalid token', [
                'id' => $request->prospect_id,
                'received' => $request->token,
                'expected' => $prospect->getUnsubscribeToken()
            ]);
            return response()->json(['message' => 'Invalid token.'], 403);
        }

        if ($prospect->unsubscribed_at) {
            return response()->json(['message' => 'Already unsubscribed.'], 200);
        }

        $prospect->update([
            'unsubscribed_at' => now(),
        ]);

        \Illuminate\Support\Facades\Log::info('Unsubscribe API: Success', ['id' => $prospect->id]);

        return response()->json([
            'message' => 'Successfully unsubscribed.',
            'prospect' => [
                'name' => $prospect->name,
                'unsubscribed_at' => $prospect->unsubscribed_at,
            ]
        ], 200);
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
