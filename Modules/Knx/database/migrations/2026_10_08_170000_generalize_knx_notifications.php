<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The notification stops being "a device the field registered" and becomes a typed
 * notice (KNX-54).
 *
 * The old row already described one event well and the office inbox depends on it, so
 * this is an ADDITIVE change: `kind` says what happened, `title`/`summary` are the
 * human line, `target` says what it is about, and the old columns stay for the rows and
 * readers that already exist. Existing rows are backfilled as `device_registered`,
 * which is exactly what they were.
 *
 * `address` and `type` become nullable because a `board_created` notice has neither:
 * forcing an address on a cabinet message would be inventing data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_notifications', function (Blueprint $table): void {
            $table->string('kind', 40)->nullable()->after('type');
            $table->string('title')->nullable()->after('kind');
            $table->text('summary')->nullable()->after('title');
            $table->foreignId('room_id')->nullable()->after('board_id')->constrained('knx_project_rooms')->nullOnDelete();
            $table->unsignedSmallInteger('module_slot')->nullable()->after('room_id');
            $table->foreignId('issue_id')->nullable()->after('device_id')->constrained('knx_conflicts')->nullOnDelete();
            $table->string('target_type', 20)->nullable()->after('serial');
            $table->string('target_id', 64)->nullable()->after('target_type');
            // The worker's own capture time, next to `reported_at` (the arrival).
            $table->timestamp('captured_at')->nullable()->after('reported_at');

            $table->index(['organization_id', 'kind']);
        });

        // A cabinet notice has no device type and a board notice has no address.
        Schema::table('knx_notifications', function (Blueprint $table): void {
            $table->string('type')->nullable()->change();
            $table->string('address', 32)->nullable()->change();
        });

        // Every existing row was a field registration; naming it is what makes the
        // filter usable from the first request.
        DB::table('knx_notifications')->whereNull('kind')->update(['kind' => 'device_registered']);
    }

    public function down(): void
    {
        Schema::table('knx_notifications', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'kind']);
            $table->dropConstrainedForeignId('issue_id');
            $table->dropConstrainedForeignId('room_id');
            $table->dropColumn([
                'kind', 'title', 'summary', 'module_slot', 'target_type', 'target_id', 'captured_at',
            ]);
        });
    }
};
