<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// LuminaireGroup is a simple name-only catalog that groups subgroups.
// We inline it here as a denormalized 'group_name' to avoid a separate table
// that adds no query value in FieldOps read paths.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_luminaire_subgroups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('group_name');  // denormalized from luminaire_groups
            $table->string('brand');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_luminaire_subgroups');
    }
};
