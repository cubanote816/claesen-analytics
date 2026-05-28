<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_tracked_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                  ->constrained('mailing_campaigns')
                  ->cascadeOnDelete();
            $table->text('original_url');
            $table->string('hash', 16);
            $table->timestamp('created_at');
            $table->unique(['campaign_id', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_tracked_links');
    }
};
