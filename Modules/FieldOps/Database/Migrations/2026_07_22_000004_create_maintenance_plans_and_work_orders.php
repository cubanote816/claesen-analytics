<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_maintenance_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('fo_maintenance_type_id')->constrained('fo_maintenance_types')->restrictOnDelete();
            $table->string('maintainable_type');
            $table->unsignedBigInteger('maintainable_id');
            $table->index(['maintainable_type', 'maintainable_id'], 'fo_maintenance_plans_maintainable_index');
            $table->foreignId('luminaire_position_id')->nullable()->constrained('fo_luminaire_positions')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('fo_clients')->nullOnDelete();
            $table->string('assigned_employee_id')->nullable();
            $table->string('recurrence_unit', 10);
            $table->unsignedSmallInteger('recurrence_interval')->default(1);
            $table->timestamp('next_due_at');
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'next_due_at']);
        });

        Schema::create('fo_maintenance_work_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('maintenance_plan_id')->nullable()->constrained('fo_maintenance_plans')->nullOnDelete();
            $table->foreignId('fo_maintenance_type_id')->constrained('fo_maintenance_types')->restrictOnDelete();
            $table->string('maintainable_type');
            $table->unsignedBigInteger('maintainable_id');
            $table->index(['maintainable_type', 'maintainable_id'], 'fo_work_orders_maintainable_index');
            $table->foreignId('luminaire_position_id')->nullable()->constrained('fo_luminaire_positions')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('fo_clients')->nullOnDelete();
            $table->string('assigned_employee_id')->nullable();
            $table->string('status', 24)->default('planned');
            $table->string('priority', 10)->default('medium');
            $table->string('source', 20)->default('backoffice');
            $table->timestamp('scheduled_for');
            $table->timestamp('due_at')->nullable();
            $table->text('problem_description')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->foreignId('started_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('completion_details')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('solution_applied')->nullable();
            $table->text('completion_notes')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('override_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('maintenance_record_id')->nullable()->unique()->constrained('fo_maintenance_records')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_for']);
            $table->index(['assigned_employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_maintenance_work_orders');
        Schema::dropIfExists('fo_maintenance_plans');
    }
};
