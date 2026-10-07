<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visit closures (V11.e, CLA-609) — roadmap §3.1 and §F.
 *
 * The three closings are **not interchangeable** (the field contract says so
 * explicitly): signing off a visit is not a partial delivery, and neither is final
 * acceptance. They share one table because they are the same act — somebody closed
 * something from site — with a different `type`, and because the office reads them
 * as one history of the project.
 *
 * `minutes` is the first thing in this domain that records time. The office's
 * `hours` report existed in its contract and refused to run for exactly that reason;
 * this is what makes it possible.
 *
 * The four lists of the closure (`pending`, `reservations`, `verifiedFunctions`,
 * `documents`) are **free text as the app sends them**: they are labels, not
 * references. Matching them to function or document rows by their text would be
 * inventing a link the app never sent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            // Resolved from the name the app sent, when the office has that room.
            $table->foreignId('room_id')->nullable()->constrained('knx_project_rooms')->nullOnDelete();
            // Only when the name did not match a room: what the technician saw is
            // never dropped, and the room row stays the authority when there is one.
            $table->string('room_label')->nullable();
            $table->string('client_id', 64)->nullable()->unique();
            $table->string('type', 20);
            $table->text('work_done');
            $table->unsignedInteger('minutes')->nullable();
            $table->string('signed_by')->nullable();
            $table->timestamp('captured_at');
            $table->foreignId('closed_by_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'project_id']);
        });

        Schema::create('knx_visit_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('visit_id')->constrained('knx_visits')->cascadeOnDelete();
            // pending | reservation | verified_function | document
            $table->string('kind', 20);
            $table->string('label', 200);
            // The order the technician wrote them in: a list read back in another
            // order is a different list.
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['visit_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_visit_items');
        Schema::dropIfExists('knx_visits');
    }
};
