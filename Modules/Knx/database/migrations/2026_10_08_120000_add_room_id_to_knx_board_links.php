<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A board link points at the canonical room, next to the source's own room text
 * (KNX-44).
 *
 * The worklist says `Room` as free text ("Polyvalente ruimte"), and a marker, a
 * device or a room sheet needs the room's identity. Resolving that text at import
 * time (by room name or by alias) turns every link into either a real `room_id` or a
 * visible `null` — the links that did NOT match stay readable with their original
 * text instead of being hidden or guessed into a wrong room.
 *
 * Nullable and `nullOnDelete`: losing a room must not delete the group-address link,
 * which is the fact the whole cabinet model is about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_board_links', function (Blueprint $table): void {
            $table->foreignId('room_id')->nullable()->after('module_id')->constrained('knx_project_rooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('knx_board_links', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('room_id');
        });
    }
};
