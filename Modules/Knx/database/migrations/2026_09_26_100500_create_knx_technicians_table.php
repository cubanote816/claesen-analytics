<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field technicians as the office app shows them: initials + full name.
 * `user_id` is optional on purpose — today the planners work with the names
 * from the ERP/team sheet, and a technician does not necessarily have a
 * backoffice account yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('initials', 8);
            $table->string('name');
            $table->timestamps();

            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_technicians');
    }
};
