<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_mirror_invoices', function (Blueprint $table) {
            $table->integer('relation_id')->nullable()->after('project_id');
            $table->date('date_expiration')->nullable()->after('date');    // invoice.date_expiration — NOT date_due
            $table->boolean('fl_paid')->default(false)->after('date_expiration');
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_mirror_invoices', function (Blueprint $table) {
            $table->dropColumn(['relation_id', 'date_expiration', 'fl_paid']);
        });
    }
};
