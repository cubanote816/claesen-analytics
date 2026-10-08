<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The floor a plan belongs to (KNX-47, asked for by Veld).
 *
 * A project has several plans — one per level — and without a floor the technician
 * cannot tell which is which, nor match a plan against the room's floor. The field is
 * nullable because the office often uploads a plan before knowing (or caring about) the
 * level, and `PATCH /documents/{id}` exists so that can be corrected later without a
 * second revision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_documents', function (Blueprint $table): void {
            $table->string('floor', 64)->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('knx_documents', function (Blueprint $table): void {
            $table->dropColumn('floor');
        });
    }
};
