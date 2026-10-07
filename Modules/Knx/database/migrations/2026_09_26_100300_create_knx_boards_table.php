<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distribution boards ("verdeelbord E10"). `code` is what the UI shows next to
 * a device; `name` is the longer label used by the field notifications
 * ("Verdeelbord E21").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_boards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_boards');
    }
};
