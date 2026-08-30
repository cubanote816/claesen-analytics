<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_maintenance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('fo_clients')->cascadeOnDelete();
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('client_portal');
            $table->string('status')->default('received')->index();
            $table->string('category')->nullable();
            $table->string('impact')->nullable();
            $table->text('description');
            $table->text('public_response')->nullable();
            $table->morphs('maintainable');
            $table->foreignId('luminaire_position_id')->nullable()->constrained('fo_luminaire_positions')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('fo_maintenance_work_orders')->nullOnDelete();
            $table->json('installation_snapshot')->nullable();
            $table->json('intake_data')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'status']);
        });

        Schema::create('fo_maintenance_request_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained('fo_maintenance_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility')->default('public')->index();
            $table->string('type')->default('message');
            $table->text('body');
            $table->timestamps();

            $table->index(['maintenance_request_id', 'visibility', 'created_at'], 'fo_request_messages_timeline_index');
        });

        Schema::create('fo_maintenance_request_work_order', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained('fo_maintenance_requests')->cascadeOnDelete();
            $table->foreignId('work_order_id')->constrained('fo_maintenance_work_orders')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['maintenance_request_id', 'work_order_id'], 'fo_request_work_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_maintenance_request_work_order');
        Schema::dropIfExists('fo_maintenance_request_messages');
        Schema::dropIfExists('fo_maintenance_requests');
    }
};
