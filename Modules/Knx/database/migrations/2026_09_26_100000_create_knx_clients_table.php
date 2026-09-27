<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KNX (Electro Bertels) — clients of the installation business.
 * Contract: docs/BACKEND-API.md §3 `Client`, §5.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('contact')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('vat', 40)->nullable();
            $table->timestamps();

            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_clients');
    }
};
