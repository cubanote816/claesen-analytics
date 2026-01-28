<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->string('id')->primary(); // String ID to match Legacy ERP
            $table->string('name');
            $table->string('function')->nullable();

            // Contact
            $table->string('mobile')->nullable();
            $table->string('email')->nullable();

            // Address Parts
            $table->string('street')->nullable();
            $table->string('zip')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            // Status & Dates
            $table->boolean('fl_active')->default(true);
            $table->dateTime('birth_date')->nullable();
            $table->dateTime('employment_date')->nullable();
            $table->dateTime('termination_date')->nullable();

            // Local Metadata
            $table->text('notes')->nullable();
            $table->string('avatar_path')->nullable(); // Optional local avatar

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
