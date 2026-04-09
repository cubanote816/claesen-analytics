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
        Schema::create('analytics_offer_simulations', function (Blueprint $table) {
            $table->id();
            $table->text('description');
            $table->string('category');
            $table->string('zipcode')->nullable();
            $table->decimal('complexity', 3, 2)->default(1.0);
            
            // AI Results
            $table->json('results');
            
            // Audit/Memory columns
            $table->json('historical_context_ids')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_offer_simulations');
    }
};
