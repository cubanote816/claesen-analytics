<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Virtual boundaries of a plan page (KNX-48): segments the office draws in Kantoor where
 * the drawing itself does not close a room (glazing the classifier does not recognise, a
 * space cut by the page edge, a partition drawn as a thin line). They are barriers for the
 * room detection only — never walls of the clean plan — and travel with the page's layer
 * roles in the same profile. Page fractions, `[[x1, y1], [x2, y2]]` per segment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_plan_clean_profiles', function (Blueprint $table): void {
            $table->json('boundaries')->nullable()->after('roles');
        });
    }

    public function down(): void
    {
        Schema::table('knx_plan_clean_profiles', function (Blueprint $table): void {
            $table->dropColumn('boundaries');
        });
    }
};
