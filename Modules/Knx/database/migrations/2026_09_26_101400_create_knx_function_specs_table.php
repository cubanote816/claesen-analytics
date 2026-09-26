<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Functional spec of a room (docs/BACKEND-API-ZONES.md §2): what the
 * installation must DO, agreed before programming, with version and approval.
 *
 * The list-shaped fields are JSON because they are free-form bullet lists
 * (triggers, conditions, group addresses, DPTs…) that the UI edits as a set —
 * normalising them into six child tables would not buy any query we need.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_function_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('knx_zones')->nullOnDelete();
            $table->string('name');
            $table->text('objective');
            $table->json('triggers')->nullable();
            $table->json('conditions')->nullable();
            $table->json('manual_controls')->nullable();
            $table->json('automations')->nullable();
            $table->text('timings')->nullable();
            $table->text('priorities')->nullable();
            $table->text('failure_behaviour')->nullable();
            $table->json('dependencies')->nullable();
            $table->json('acceptance_criteria')->nullable();
            $table->json('group_addresses')->nullable();
            $table->json('dpts')->nullable();
            $table->json('knx_objects')->nullable();
            $table->string('status', 12)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->string('author_name')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_function_specs');
    }
};
