<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_events', function (Blueprint $table) {
            $table->id();

            // event_name/app are validated against Enums\EventName / Enums\AppSource at
            // the request layer (StoreAppEventRequest) rather than a DB ENUM column —
            // adding a new event to the catalog must never require an ALTER TABLE.
            $table->string('event_name', 100);
            $table->string('app', 50);

            // Nullable: anonymous / pre-session events (e.g. a failed login attempt)
            // are a legitimate signal, not an error case.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('session_id', 64);

            // Soft reference to the MySQL mirror of employees.id (non-incrementing
            // string PK), same pattern as Safety::incident_worker_id and
            // FieldOps::FoMaintenanceRecord.employee_id — no FK across module data.
            $table->string('employee_id')->nullable();

            $table->string('entity_type', 100)->nullable();
            $table->string('entity_id', 100)->nullable();

            // Role(s) the user held at the moment of the event, denormalized —
            // roles can change over time and we want what applied then, not now.
            $table->json('role_snapshot')->nullable();

            $table->json('properties');
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
            $table->index('app');
            $table->index('event_name');
            $table->index('occurred_at');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_events');
    }
};
