<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('safety_checklists', function (Blueprint $table) {
            $table->enum('type', ['inspection', 'incident'])->default('inspection')->after('name');
        });

        Schema::table('safety_inspections', function (Blueprint $table) {
            $table->enum('type', ['inspection', 'incident'])->default('inspection')->after('checklist_id');
            $table->foreignId('incident_worker_id')->nullable()->after('type')->constrained('users');
        });

        Schema::create('safety_inspection_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('safety_inspections')->cascadeOnDelete();
            $table->foreignId('worker_id')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safety_inspection_workers');

        Schema::table('safety_inspections', function (Blueprint $table) {
            $table->dropForeign(['incident_worker_id']);
            $table->dropColumn(['type', 'incident_worker_id']);
        });

        Schema::table('safety_checklists', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
