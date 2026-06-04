<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_publication_states', function (Blueprint $table) {
            $table->id();

            // State machine: idle → pending → accepted | error
            // 'accepted' means the frontend responded 202 (request accepted),
            // NOT that the Astro build has finished. Real build status lives
            // in GET /health on the frontend receiver.
            $table->string('status')->default('idle');

            // Debounce token: each requestRebuild() generates a new UUID and
            // stores it here. TriggerStaticSiteRebuildJob compares its own key
            // against this value; a mismatch means a newer job superseded it.
            $table->uuid('dispatch_key')->nullable();
            $table->timestamp('dispatched_at')->nullable();

            // Timestamp of the first change in the current pending cycle.
            // Set only once per cycle (only if null); cleared on markAccepted().
            $table->timestamp('pending_since')->nullable();

            // Last time the frontend webhook responded with 202.
            $table->timestamp('last_accepted_at')->nullable();

            // Last error details (cleared on markAccepted / markPending).
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_publication_states');
    }
};
