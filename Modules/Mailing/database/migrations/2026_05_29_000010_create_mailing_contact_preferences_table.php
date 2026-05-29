<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_contact_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')
                  ->constrained('prospects_prospects')
                  ->cascadeOnDelete();
            $table->string('category', 100);
            $table->boolean('subscribed')->default(true);
            $table->timestamps();

            $table->unique(['prospect_id', 'category']);
            $table->index(['category', 'subscribed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_contact_preferences');
    }
};
