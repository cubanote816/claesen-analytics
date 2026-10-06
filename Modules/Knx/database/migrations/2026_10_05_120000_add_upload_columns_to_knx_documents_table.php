<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Uploading plans from the office (KNX-3, docs/BACKEND-API.md §4.11).
 *
 * Two columns change, and both are about the upload being the first writer of a
 * document:
 *
 *   - `client_id` gives the office the same idempotency the field app already has.
 *     A 3 MB upload interrupted by the network and retried by the user would
 *     otherwise create a duplicate revision, which is a data problem, not a UI
 *     one. Nullable: only uploaded documents carry the key, and the demo fixture
 *     does not need one.
 *
 *   - `size_bytes` stops being nullable. A document without a size was possible
 *     because nothing ever wrote one; the upload has the bytes in hand. Existing
 *     rows are backfilled to 0 (there is no honest size to invent), so the
 *     NOT NULL change can never fail on old data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_documents', function (Blueprint $table): void {
            $table->string('client_id', 64)->nullable()->after('id');
            $table->unique('client_id');
        });

        // A null size is not reconstructible; 0 is the only non-invented value, and
        // it only ever applies to rows written before this migration existed.
        DB::table('knx_documents')->whereNull('size_bytes')->update(['size_bytes' => 0]);

        Schema::table('knx_documents', function (Blueprint $table): void {
            $table->unsignedBigInteger('size_bytes')->default(0)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('knx_documents', function (Blueprint $table): void {
            $table->unsignedBigInteger('size_bytes')->nullable()->change();
            $table->dropUnique(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
