<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stub client catalog — FieldOps needs a client reference for complexes.
// Full client management is out of scope for Slice C; this table is read-only
// from a FieldOps perspective.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('street')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('language', 10)->default('nl');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_clients');
    }
};
