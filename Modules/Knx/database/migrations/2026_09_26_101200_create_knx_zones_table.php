<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site-readiness zones (docs/BACKEND-API-ZONES.md §1).
 *
 * There is NO status column on purpose: the contract says the status is
 * derived on every read and every write from `knx_zone_checks`, and that it is
 * never set by hand. Caching it would be a second source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('knx_project_rooms')->nullOnDelete();
            $table->string('name');
            $table->string('floor')->nullable();
            $table->date('next_review_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_zones');
    }
};
