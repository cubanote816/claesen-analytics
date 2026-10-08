<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field writes for cabinets (KNX-53) and the device's place inside one.
 *
 * A cabinet created from site needs the same idempotency every other field write has:
 * the app may be offline for hours and retry, so `client_id` decides identity and a
 * repeat answers the row that already exists instead of creating a second one. The
 * unique index is what makes that race-safe; the service's read is only the fast path.
 *
 * `knx_devices.module_slot` and `channel` let a registration say WHERE on the board the
 * apparatus sits, next to the board it already points at. Both nullable: a registration
 * that only knows the board is still a valid registration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_boards', function (Blueprint $table): void {
            $table->string('client_id', 64)->nullable()->after('slug');
            // Evidence of the cabinet's interior sent from site. Stored now; the
            // photos entity (KNX-26) will expose it as a proper photo.
            $table->string('photo_path')->nullable()->after('floor');
            // MySQL allows several NULLs under a unique index, so imported and
            // office-created boards never collide with each other.
            $table->unique('client_id');
        });

        Schema::table('knx_board_modules', function (Blueprint $table): void {
            $table->string('client_id', 64)->nullable()->after('slot');
            $table->string('photo_path')->nullable()->after('client_id');
            $table->unique('client_id');
        });

        Schema::table('knx_devices', function (Blueprint $table): void {
            $table->unsignedSmallInteger('module_slot')->nullable()->after('board_id');
            $table->string('channel', 8)->nullable()->after('module_slot');
        });
    }

    public function down(): void
    {
        Schema::table('knx_boards', function (Blueprint $table): void {
            $table->dropUnique(['client_id']);
            $table->dropColumn(['client_id', 'photo_path']);
        });

        Schema::table('knx_board_modules', function (Blueprint $table): void {
            $table->dropUnique(['client_id']);
            $table->dropColumn(['client_id', 'photo_path']);
        });

        Schema::table('knx_devices', function (Blueprint $table): void {
            $table->dropColumn(['module_slot', 'channel']);
        });
    }
};
