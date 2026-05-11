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
        Schema::create('safety_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('safety_inspections')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('safety_questions');
            $table->enum('status', ['ok', 'nok', 'na']);
            $table->text('remark')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safety_answers');
    }
};
