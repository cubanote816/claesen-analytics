<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// safety_id and access_id are nullable external references — Safety and Access
// are out of scope for FieldOps Slice C. They are stored as plain integer IDs
// (no FK constraint) so Sport data can be imported without creating Safety/Access
// records in Core.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('structure_type_id')->constrained('fo_structure_types')->restrictOnDelete();
            $table->integer('height')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('info')->nullable(); // translatable: {nl, fr, en, es}
            $table->unsignedBigInteger('external_safety_id')->nullable();   // bridge to Safety module
            $table->unsignedBigInteger('external_access_id')->nullable();   // bridge to Access (future)
            $table->integer('cafca_material_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_structures');
    }
};
