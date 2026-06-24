<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('safety_inspections', function (Blueprint $table) {
            $table->timestamp('report_emailed_at')->nullable()->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('safety_inspections', function (Blueprint $table) {
            $table->dropColumn('report_emailed_at');
        });
    }
};
