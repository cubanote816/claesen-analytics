<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each board sits on a revision of the plan (KNX-3, docs/BACKEND-API.md §4.11).
 *
 * The set belongs to a DOCUMENT —a revision—, never to the project: there are no
 * portable "project markers". That is why the upload and the marking ship together:
 * a marker is a fraction of a specific drawing, and when the next revision arrives
 * the previous markers stay attached to *their* document and keep being correct for
 * it.
 *
 * `board_id` is a **string**, not a foreign key: it is the board's external identity
 * (the worklist slug the boards endpoint exposes as `id`, e.g.
 * `caja-1-alsb-leefgroep-gelijkvloers-glv`). The office can place a marker before the
 * board import exists, and a numeric id generated here would not match the identity
 * the front already uses.
 *
 * `page_width_pt`/`page_height_pt` are the client's own measurement, stored as opaque
 * data: the server never parses the PDF. They are nullable because an honest client
 * may not know them yet, and `nx`/`ny` are fractions of the page, which is what makes
 * them survive zoom, devicePixelRatio and a renderer change.
 *
 * The unique key is one marker per board per document: a board is in one place, so a
 * second row for the same board on another page would be a contradiction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_plan_markers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('knx_documents')->cascadeOnDelete();
            $table->string('board_id', 128);
            $table->unsignedInteger('page');
            $table->decimal('page_width_pt', 12, 4)->nullable();
            $table->decimal('page_height_pt', 12, 4)->nullable();
            // decimal(6,5): five decimals on a fraction of a page is far more
            // resolution than a drawing needs, and it is what the contract types.
            $table->decimal('nx', 6, 5);
            $table->decimal('ny', 6, 5);
            $table->foreignId('placed_by_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'document_id', 'board_id']);
            $table->index(['project_id', 'document_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_plan_markers');
    }
};
