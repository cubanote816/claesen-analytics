<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soft reference to intelligence_mirror_relations.id (CAFCA ERP relation.id),
     * mirroring the cross-module pattern used by Safety::incident_worker_id and
     * FieldOps maintenance records' employee_id — no FK, validated in app layer.
     * Nullable: manually-entered clients that predate the sync keep no reference.
     */
    public function up(): void
    {
        Schema::table('fo_clients', function (Blueprint $table) {
            $table->unsignedInteger('relation_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('fo_clients', function (Blueprint $table) {
            $table->dropColumn('relation_id');
        });
    }
};
