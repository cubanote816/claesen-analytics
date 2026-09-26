<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plans and documents of a project.
 *
 * `size_bytes` is stored raw and the API formats it, because the contract's own
 * note says either is acceptable but the choice must be documented: keeping the
 * number is lossless and lets the front format later without a migration. The
 * response still carries the contract field `size` as human text ("4,2 MB") so
 * the front needs no change.
 *
 * `url` is never stored: GET /documents/{id} returns a signed temporary URL.
 * `revision` / `is_current` / `approved_by_name` come from the document-review
 * quick win of the roadmap (§3.7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 40);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('path')->nullable();
            $table->string('revision', 20)->nullable();
            $table->boolean('is_current')->default(true);
            $table->string('approved_by_name')->nullable();
            $table->string('uploaded_by_name')->nullable();
            $table->date('uploaded_at');
            $table->timestamps();

            $table->index(['organization_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_documents');
    }
};
