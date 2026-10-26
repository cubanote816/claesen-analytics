<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rooms/spaces of a project. Zones (site readiness, BACKEND-API-ZONES.md §1)
 * hang off these: a zone is a room that must reach "ready for integration".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_project_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('floor')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_project_rooms');
    }
};
