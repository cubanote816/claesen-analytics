<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fo_maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('fo_maintenance_type_id')->constrained('fo_maintenance_types')->restrictOnDelete();

            // polymorphic target being maintained: Luminaire or ElectricalBoard
            $table->morphs('maintainable');

            // employee.id (MySQL local mirror table, non-incrementing string PK) —
            // soft reference, no DB FK (different module, same pattern as
            // Modules\Safety\Models\Inspection::incident_worker_id).
            $table->string('employee_id')->nullable();

            // client who reported the issue (nullable — only set for reported_by_client records)
            $table->foreignId('client_id')->nullable()->constrained('fo_clients')->nullOnDelete();

            $table->timestamp('maintenance_at');
            $table->json('details')->nullable(); // checklist of tasks performed, varies by equipment type
            $table->text('notes')->nullable();

            // incident / corrective / emergency tracking
            $table->text('problem_description')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('solution_applied')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->timestamp('problem_reported_at')->nullable();
            $table->timestamp('problem_solved_at')->nullable();
            $table->decimal('downtime_hours', 8, 2)->nullable();

            // client-reported service flow
            $table->boolean('reported_by_client')->default(false);
            $table->string('priority', 10)->nullable(); // high|medium|low
            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('location_details')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fo_maintenance_records');
    }
};
