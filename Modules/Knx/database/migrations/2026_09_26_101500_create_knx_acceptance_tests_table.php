<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acceptance tests (docs/BACKEND-API-ZONES.md §3). A test hangs off a
 * *function*, not only off a device — that is what lets the office answer
 * "what was verified, and against which agreed behaviour".
 *
 * `physical_check` separates "the KNX state changed" from "the lamp actually
 * came on", which the contract calls out explicitly.
 * `zone_name` is not stored: it is derived through the function's zone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_acceptance_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->foreignId('function_id')->nullable()->constrained('knx_function_specs')->nullOnDelete();
            $table->text('action');
            $table->text('expected');
            $table->text('observed')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('note')->nullable();
            $table->boolean('physical_check')->default(false);
            $table->string('executor_name')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->json('evidence_urls')->nullable();
            $table->string('issue_id')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_acceptance_tests');
    }
};
