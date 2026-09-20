<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F4/CLA-477 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Estados nuevo, asignado, en curso, esperando cliente, cerrado y spam" —
 * the ticket's own acceptance wording — replaces the ad-hoc CRM-style set
 * this table shipped with (pending/contacted/in_progress/completed/
 * cancelled, 2026_02_07_110504) with a real lead-inbox workflow. Data
 * migration, separate from the structure change (D7) — raw DB::table(),
 * never Eloquent (a bulk status flip through the model would fire
 * ConsultationRequestObserver::updated() for every historical row,
 * writing a wall of fake "status changed" activity log entries that
 * never actually happened).
 *
 * Mapping (forward, up()):
 *   pending      -> new           (nobody has acted on it yet)
 *   contacted    -> assigned      (closest existing signal for "has an owner")
 *   in_progress  -> in_progress   (unchanged)
 *   completed    -> closed        (terminal, successfully handled)
 *   cancelled    -> closed        (terminal, not converted — 'spam' is
 *                                  reserved for the new workflow going
 *                                  forward; a genuinely legitimate but
 *                                  cancelled historical lead must never be
 *                                  retroactively mislabeled as spam)
 *
 * Reverse (down()) is deliberately imperfect where the forward map
 * collapses two old values into one, or where a state (waiting_client,
 * spam) has no historical equivalent at all — documented here, never
 * silent, and never data-destructive (only a label approximation, no
 * rows touched beyond this one column):
 *   new            -> pending
 *   assigned       -> contacted
 *   in_progress    -> in_progress
 *   waiting_client -> in_progress (closest predates-this-state equivalent)
 *   closed         -> completed   (collapses what may have originally
 *                                  been 'cancelled' — the forward map
 *                                  already merged the two; a round trip
 *                                  through down() then up() is idempotent
 *                                  regardless, since 'completed' maps
 *                                  forward to 'closed' again)
 *   spam           -> cancelled   (closest predates-this-state equivalent)
 */
return new class extends Migration
{
    private const FORWARD = [
        'pending' => 'new',
        'contacted' => 'assigned',
        'completed' => 'closed',
        'cancelled' => 'closed',
    ];

    private const BACKWARD = [
        'new' => 'pending',
        'assigned' => 'contacted',
        'waiting_client' => 'in_progress',
        'closed' => 'completed',
        'spam' => 'cancelled',
    ];

    public function up(): void
    {
        foreach (self::FORWARD as $old => $new) {
            DB::table('website_consultation_requests')->where('status', $old)->update(['status' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::BACKWARD as $new => $old) {
            DB::table('website_consultation_requests')->where('status', $new)->update(['status' => $old]);
        }
    }
};
