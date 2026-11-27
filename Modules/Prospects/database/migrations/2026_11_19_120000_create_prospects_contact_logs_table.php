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
        Schema::create('prospects_contact_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prospect_id')->constrained('prospects_prospects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('contacted_at');
            $table->string('direction', 20); // inbound, outbound
            $table->string('method', 20); // phone, email, in_person, website_form, other
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['prospect_id', 'contacted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prospects_contact_logs');
    }
};
