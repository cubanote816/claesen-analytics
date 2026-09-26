<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conflict between the ETS plan and what is on site. The whole point of the
 * Conflictencentrum: the office decides, and the outcome is an ETS correction
 * *worklist* — never an automatic ETS write.
 *
 * `status` has the eight states the office app implements (BACKEND-API.md §3
 * predates the extension and lists four; the front's types.ts is the source of
 * truth): open → in_review → ets_listed → applied → downloaded → verified →
 * closed, plus rejected.
 *
 * `proposal` is the new address the office proposes; whether it is already in
 * use is validated in the service (409 address_in_use), not by a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('knx_devices')->nullOnDelete();
            $table->string('severity', 10);
            $table->string('type', 30);
            $table->string('address', 32);
            $table->text('device_existing')->nullable();
            $table->text('device_field')->nullable();
            $table->string('reported_by_name')->nullable();
            $table->timestamp('reported_at');
            $table->text('note')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('open');
            $table->string('proposal', 32)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['project_id', 'address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_conflicts');
    }
};
