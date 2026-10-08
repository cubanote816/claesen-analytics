<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The room model of a plan revision, per page (KNX-45).
 *
 * The geometry hangs off the DOCUMENT —a revision— and a page, never off the project:
 * the measurement was explicit that the fidelidad comes from NOT regenerating the plan,
 * so every polygon is only meaningful against the exact drawing it was drawn on. Uploading
 * a new revision must leave the previous one's model where it was.
 *
 * The geometry is opaque data, the same rule as the board markers: `polygon` and
 * `label_at` are stored as the client measured them and the server never parses the PDF.
 * `state` is provenance (`proposed` vs `confirmed`) because the field proposes and the
 * office decides: a polygon that came from an extraction or a carry-over is not a fact
 * until a person confirms it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_plan_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('knx_documents')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('knx_project_rooms')->cascadeOnDelete();
            $table->unsignedInteger('page');
            // [[nx, ny], …] as fractions of the page. Opaque.
            $table->json('polygon');
            $table->json('label_at')->nullable();
            $table->decimal('area_m2', 10, 2)->nullable();
            // manual | pdf | dwg
            $table->string('source', 10);
            // proposed | confirmed
            $table->string('state', 12)->default('proposed');
            $table->foreignId('placed_by_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->decimal('page_width_pt', 12, 4)->nullable();
            $table->decimal('page_height_pt', 12, 4)->nullable();
            $table->timestamps();

            // A room is in one place on one drawing: the same rule as a board marker.
            $table->unique(['organization_id', 'document_id', 'room_id']);
            $table->index(['project_id', 'document_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_plan_rooms');
    }
};
