<?php

namespace Modules\Mailing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\MessageEvent;
use Modules\Mailing\Models\TrackedLink;

class TrackingController extends Controller
{
    private const TRANSPARENT_GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    /**
     * MAI-013 — Serve a 1×1 tracking pixel and record an opened event.
     *
     * Token may arrive with a .gif suffix (added by the URL generator for
     * email-client compatibility). It is stripped before the DB lookup.
     */
    public function openPixel(Request $request, string $token): Response
    {
        $token   = preg_replace('/\.gif$/i', '', $token);
        $message = CampaignMessage::where('tracking_token', $token)->first();

        if ($message) {
            $duplicateWithinWindow = $message->events()
                ->where('event_type', MessageEventType::OPENED->value)
                ->where('occurred_at', '>=', now()->subSeconds(30))
                ->whereJsonContains('metadata->ip', $request->ip())
                ->exists();

            if (! $duplicateWithinWindow) {
                MessageEvent::create([
                    'message_id'  => $message->id,
                    'event_type'  => MessageEventType::OPENED,
                    'occurred_at' => now(),
                    'metadata'    => [
                        'ip'         => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ],
                ]);
            }
        }

        return response(base64_decode(self::TRANSPARENT_GIF), 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
            'Expires'       => '0',
        ]);
    }

    /**
     * MAI-014 — Record a click event and redirect to the original URL.
     *
     * Falls back to APP_URL on unknown token or hash to avoid error 500.
     */
    public function clickRedirect(Request $request, string $token, string $hash): RedirectResponse
    {
        $fallback = config('app.url');

        $message = CampaignMessage::where('tracking_token', $token)->first();
        if (! $message) {
            return redirect($fallback);
        }

        $link = TrackedLink::where('campaign_id', $message->campaign_id)
            ->where('hash', $hash)
            ->first();

        if (! $link) {
            return redirect($fallback);
        }

        MessageEvent::create([
            'message_id'  => $message->id,
            'event_type'  => MessageEventType::CLICKED,
            'occurred_at' => now(),
            'metadata'    => [
                'link_url'   => $link->original_url,
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);

        return redirect($link->original_url);
    }
}
