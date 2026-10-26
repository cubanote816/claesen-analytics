<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only history of a conflict: every status change writes one row
 * (PATCH /conflicts/{id} does it in the same transaction). `address` records
 * the proposed address *at that moment*, because the history is what ends up
 * as the ETS correction worklist line.
 *
 * No created_at/updated_at: the contract's `ConflictLogEntry.at` is the domain
 * timestamp (set by the writer), and nothing reads model timestamps here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_conflict_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conflict_id')->constrained('knx_conflicts')->cascadeOnDelete();
            $table->timestamp('at');
            $table->string('action', 20);
            $table->string('address', 32)->nullable();

            $table->index(['conflict_id', 'at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_conflict_logs');
    }
};
