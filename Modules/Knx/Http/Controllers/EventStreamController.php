<?php

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Services\EventStreamService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * `GET /events` — server-sent events (§6).
 *
 * Two deliberate choices:
 *
 * **The connection is bounded.** §6 does not say it, but a PHP worker held open
 * forever is a worker the rest of the office does not get. Each connection lives
 * `config('knx.events.stream_seconds')` and then closes with a comment; the browser
 * reconnects on its own, which is exactly how EventSource is meant to be used.
 *
 * **Bearer, not a query parameter.** A token in a query string ends up in access
 * logs and browser history. That means the client cannot use the native
 * `EventSource` — which cannot send headers — and has to read the stream from
 * `fetch()` instead.
 */
class EventStreamController extends Controller
{
    public function stream(Request $request, EventStreamService $events): StreamedResponse
    {
        $seconds = (int) config('knx.events.stream_seconds');
        $interval = (int) config('knx.events.poll_seconds');
        $cursor = $events->currentCursor();

        return response()->stream(function () use ($events, $cursor, $seconds, $interval): void {
            // A browser closing the tab must not keep the loop alive.
            ignore_user_abort(true);

            $deadline = microtime(true) + $seconds;

            // Tells the client how long to wait before reconnecting.
            echo 'retry: '.($interval * 1000)."\n\n";
            $this->flush();

            while (microtime(true) < $deadline) {
                if (connection_aborted() !== 0) {
                    return;
                }

                $pending = $events->pendingSince($cursor);
                $cursor = $pending['cursor'];

                foreach ($pending['events'] as $event) {
                    echo 'event: '.$event['event']."\n";
                    echo 'data: '.json_encode($event['data'], JSON_THROW_ON_ERROR)."\n\n";
                }

                $this->flush();
                sleep($interval);
            }

            // A polite close: the client reconnects and picks up from the present.
            echo ": bye\n\n";
            $this->flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            // Nginx and friends love to buffer a response until it is complete, which
            // for a stream is never. Without this the events arrive in one lump.
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function flush(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}
