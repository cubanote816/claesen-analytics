<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Symbol roles of a plan page (KNX-48, plano IA v2): `{symbolId: furniture|device|hidden|unknown}`
 * next to the group roles, so a person's correction of a symbol survives a reload. The symbol
 * id (`s<index>`) is resolved by Kantoor from the same processing version; the server stores
 * the map as sent. Additive and nullable: an older profile simply has none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_plan_clean_profiles', function (Blueprint $table): void {
            $table->json('symbol_roles')->nullable()->after('boundaries');
        });
    }

    public function down(): void
    {
        Schema::table('knx_plan_clean_profiles', function (Blueprint $table): void {
            $table->dropColumn('symbol_roles');
        });
    }
};
