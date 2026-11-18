<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who reconciled an `uncertain` reservation by hand, and the optional note (ids and a
 * short free text of the admin: never plan content).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_ai_spend_entries', function (Blueprint $table): void {
            $table->foreignId('reconciled_by_employee_id')->nullable()->after('created_by_employee_id')
                ->constrained('knx_employees')->nullOnDelete();
            $table->string('reconciled_note', 200)->nullable()->after('reconciled_by_employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('knx_ai_spend_entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reconciled_by_employee_id');
            $table->dropColumn('reconciled_note');
        });
    }
};
