<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rooms become canonical and carry their own names from other vocabularies; zones
 * become one-per-room (KNX-44).
 *
 * The measurement (`planos-extraccion-medicion.md`) found THREE room vocabularies on
 * the same project: 85 `Space` rows in ETS, 79 names in the harness room-linking
 * report and 25 names in the worklist. They are not reconciled, and the office's own
 * room is the canonical one — so the other names are kept as aliases instead of being
 * forced into a single string that would lose which source said what.
 *
 * `knx_zones.room_id` gets its unique index here (zona = habitación) and the migration
 * backfills a zone, with its eight checks, for every room that does not have one yet.
 * Zones that belong to no room are LEFT ALONE and stay readable with `roomId: null`:
 * the demo fixture has zone rows that are not rooms, and deleting office data to
 * satisfy a constraint is not this migration's job.
 */
return new class extends Migration
{
    public function up(): void
    {
        // DDL auto-commits in MySQL, so a failure halfway leaves the table behind and
        // the migration unrecorded: every step has to be safe to re-run.
        if (! Schema::hasTable('knx_room_aliases')) {
            Schema::create('knx_room_aliases', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('room_id')->constrained('knx_project_rooms')->cascadeOnDelete();
                // Which source said this name: ets | worklist | plan | manual.
                $table->string('source', 16);
                $table->string('name', 255);
                $table->timestamps();

                // The same source cannot say the same thing twice, but ETS and the
                // worklist may agree on a name: that is still two facts.
                $table->unique(['room_id', 'source', 'name']);
                // The import resolves a worklist room name to a room id through this index.
                $table->index(['source', 'name']);
            });
        }

        $this->backfillOneZonePerRoom();

        if (! $this->hasUniqueRoomIndex()) {
            Schema::table('knx_zones', function (Blueprint $table): void {
                // Zona = habitación. Nullable because a zone without a room is a real,
                // kept state; MySQL allows several NULLs under a unique index.
                $table->unique('room_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('knx_zones', function (Blueprint $table): void {
            $table->dropUnique(['room_id']);
        });

        Schema::dropIfExists('knx_room_aliases');
    }

    /**
     * Give every room that has no zone one, with the eight checks in pending state.
     *
     * Written with the query builder and not the models on purpose: a migration must
     * not depend on model code that will keep changing after it ran.
     */
    private function backfillOneZonePerRoom(): void
    {
        $keys = (array) config('knx.zone_check_keys', []);
        $now = now();

        $rooms = DB::table('knx_project_rooms')
            ->leftJoin('knx_zones', 'knx_zones.room_id', '=', 'knx_project_rooms.id')
            ->whereNull('knx_zones.id')
            ->select('knx_project_rooms.id', 'knx_project_rooms.name', 'knx_project_rooms.floor', 'knx_project_rooms.project_id')
            ->get();

        foreach ($rooms as $room) {
            $organizationId = DB::table('knx_projects')
                ->where('id', $room->project_id)
                ->value('organization_id');

            if ($organizationId === null) {
                continue;
            }

            $zoneId = DB::table('knx_zones')->insertGetId([
                'organization_id' => $organizationId,
                'project_id' => $room->project_id,
                'room_id' => $room->id,
                'name' => $room->name,
                'floor' => $room->floor,
                'next_review_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($keys as $key) {
                // `knx_zone_checks` deliberately has no `created_at`: its `updated_at` is
                // the domain timestamp and stays null until someone touches the check.
                DB::table('knx_zone_checks')->insert([
                    'zone_id' => $zoneId,
                    'key' => $key,
                    'status' => 'pending',
                    'note' => null,
                    'updated_by_employee_id' => null,
                ]);
            }
        }
    }

    private function hasUniqueRoomIndex(): bool
    {
        foreach (Schema::getIndexes('knx_zones') as $index) {
            if ($index['columns'] === ['room_id'] && $index['unique'] === true) {
                return true;
            }
        }

        return false;
    }
};
