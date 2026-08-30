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
        Schema::create('website_consultation_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('company')->nullable();

            $table->string('type')->default('consultation'); // consultation, quote, project
            $table->string('project_type')->nullable(); // sport, industrial, public, masts, other
            $table->text('message');
            $table->string('preferred_contact')->default('email'); // email, phone
            $table->string('status')->default('pending'); // pending, contacted, in_progress, completed, cancelled

            $table->text('internal_notes')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('priority')->default('medium'); // low, medium, high, urgent
            $table->string('source')->nullable();

            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->decimal('actual_value', 15, 2)->nullable();
            $table->string('currency')->default('EUR');

            $table->date('follow_up_date')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->json('tags')->nullable();
            $table->json('custom_fields')->nullable();

            $table->timestamp('last_activity_at')->nullable();
            $table->integer('activity_count')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_consultation_requests');
    }
};
