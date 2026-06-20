<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop FK to users on pivot table and change to varchar to match employees.id (varchar PK)
        Schema::table('safety_inspection_workers', function (Blueprint $table) {
            $table->dropForeign('safety_inspection_workers_worker_id_foreign');
            $table->string('worker_id')->change();
        });

        // Drop FK to users on inspections and change to varchar
        Schema::table('safety_inspections', function (Blueprint $table) {
            $table->dropForeign('safety_inspections_incident_worker_id_foreign');
            $table->string('incident_worker_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('safety_inspection_workers', function (Blueprint $table) {
            $table->unsignedBigInteger('worker_id')->change();
            $table->foreign('worker_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('safety_inspections', function (Blueprint $table) {
            $table->unsignedBigInteger('incident_worker_id')->nullable()->change();
            $table->foreign('incident_worker_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
