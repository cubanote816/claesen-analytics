<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field registrations (V11.c, CLA-609).
 *
 * `client_id` is the idempotency key the field app generates on the device before
 * it ever has a connection. It is unique **globally**, not per project: a UUID made
 * on a phone cannot collide across projects, and a global key means a retry that
 * arrives after the technician moved on can still be recognised.
 *
 * `captured_at` is when the technician registered the apparatus, which can be long
 * before the server saw it: `registered_at` is what the office displays, and the
 * row's own `created_at` already records the arrival.
 *
 * No `captured_offline` flag: the app does not need to say "I was offline", and
 * `captured_at < created_at` already tells that story without a second source of
 * truth that could disagree with the timestamps.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_devices', function (Blueprint $table): void {
            $table->string('client_id', 64)->nullable()->unique()->after('id');
            $table->timestamp('captured_at')->nullable()->after('registered_at');
            $table->string('photo_path')->nullable()->after('captured_at');
        });
    }

    public function down(): void
    {
        Schema::table('knx_devices', function (Blueprint $table): void {
            $table->dropUnique(['client_id']);
            $table->dropColumn(['client_id', 'captured_at', 'photo_path']);
        });
    }
};
