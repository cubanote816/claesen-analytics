<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_mirror_costs', function (Blueprint $table) {
            // Maps to followup_cost.already_invoiced (bit).
            // Use already_invoiced, NOT invoice (which flags line type) nor fl_booked_to_invoice (1 row only).
            $table->boolean('invoiced')->default(false)->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_mirror_costs', function (Blueprint $table) {
            $table->dropColumn('invoiced');
        });
    }
};
