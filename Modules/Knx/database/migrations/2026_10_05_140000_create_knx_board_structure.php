<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The structure of a distribution board: its modules, their channels and the links
 * that tie a channel to a room, a communication object and a group address (KNX-3).
 *
 * Until now `knx_boards` was only a label a device could point at. The office's
 * Verdelers tab needs the real thing, and measuring the project's own worklist (160
 * rows, 5 boards, 13 modules, 52 channels) fixed two model requirements:
 *
 *   1. **Modules need instance identity.** The worklist only carries the order number
 *      (`SA/S4.16.2.2`), so two identical modules on one board would be
 *      indistinguishable. `slot` is that identity, derived from the worklist order.
 *   2. **A channel serves ONE room and carries several communication objects.** Not a
 *      single channel of the 52 serves more than one room; what repeats per channel is
 *      the objects of that same room. So the fact is the link
 *      `(channel, room, object, group address)`, and `knx_board_links` stores exactly
 *      that instead of a `channel -> room` field.
 *
 * `knx_boards.slug` is the board's external identity — the worklist slug, the same
 * string a marker's `boardId` carries — and it is separate from `code` because the
 * code is the short label the UI shows next to a device (`E10`, `caja-1`) and the slug
 * is longer. Nullable: field registrations create a board with a code and no slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_boards', function (Blueprint $table): void {
            // The worklist slug, e.g. "caja-1-alsb-leefgroep-gelijkvloers-glv".
            $table->string('slug', 128)->nullable()->after('code');
            $table->string('floor', 64)->nullable()->after('name');
            // MySQL allows several NULLs under a unique index, so boards with only a
            // code (from the field) do not collide with each other.
            $table->unique(['project_id', 'slug']);
        });

        Schema::create('knx_board_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('board_id')->constrained('knx_boards')->cascadeOnDelete();
            // The order number of the actuator/dimmer/gateway, e.g. "SA/S4.16.2.2".
            $table->string('device', 64);
            // 1-based position on the board: the module's identity of instance.
            $table->unsignedSmallInteger('slot');
            $table->timestamps();

            $table->unique(['board_id', 'slot']);
        });

        Schema::create('knx_board_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('module_id')->constrained('knx_board_modules')->cascadeOnDelete();
            $table->string('channel', 8);
            $table->string('room', 150);
            $table->string('object', 150);
            // switch_cmd, switch_fb, shade_move_cmd… (unknown when the GA list has none).
            $table->string('role', 64);
            $table->string('ga', 32);
            $table->string('ga_name', 255);
            $table->string('dpt', 32);
            // Keeps the worklist's own order so the panel renders it as it was written.
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['module_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_board_links');
        Schema::dropIfExists('knx_board_modules');

        Schema::table('knx_boards', function (Blueprint $table): void {
            $table->dropUnique(['project_id', 'slug']);
            $table->dropColumn(['slug', 'floor']);
        });
    }
};
