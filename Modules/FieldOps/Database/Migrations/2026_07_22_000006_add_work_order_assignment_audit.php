<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fo_maintenance_work_orders', function (Blueprint $table): void {
            $table->foreignId('assigned_by_user_id')->nullable()->after('assigned_employee_id')->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable()->after('assigned_by_user_id');
            $table->timestamp('returned_at')->nullable()->after('submitted_at');
            $table->foreignId('returned_by_user_id')->nullable()->after('returned_at')->constrained('users')->nullOnDelete();
            $table->text('return_reason')->nullable()->after('returned_by_user_id');
        });

        Schema::create('fo_maintenance_work_order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_order_id')->constrained('fo_maintenance_work_orders')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->string('from_assigned_employee_id')->nullable();
            $table->string('to_assigned_employee_id')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['work_order_id', 'occurred_at'], 'fo_work_order_events_order_time_index');
            $table->index(['event_type', 'occurred_at'], 'fo_work_order_events_type_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_maintenance_work_order_events');

        Schema::table('fo_maintenance_work_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('returned_by_user_id');
            $table->dropColumn(['returned_at', 'return_reason']);
            $table->dropConstrainedForeignId('assigned_by_user_id');
            $table->dropColumn('assigned_at');
        });
    }
};
