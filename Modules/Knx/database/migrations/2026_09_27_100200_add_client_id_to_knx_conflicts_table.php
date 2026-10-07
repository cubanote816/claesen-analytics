<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The field app's idempotency key on a conflict (V11.c/V11.d, CLA-609).
 *
 * Two different field actions end up as a conflict, and both can be retried:
 *   - a device registration whose address is already taken (V11.c) records the
 *     attempt as a `duplicate_address` conflict;
 *   - a reported incident (V11.d) is itself a conflict.
 *
 * Without this column a retry would leave a second, identical conflict in the
 * office's worklist. With it, the second attempt is recognised as the same one —
 * the same rule the field uses everywhere else.
 *
 * Nullable: every conflict the office raises itself has no `clientId`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_conflicts', function (Blueprint $table): void {
            $table->string('client_id', 64)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('knx_conflicts', function (Blueprint $table): void {
            $table->dropUnique(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
