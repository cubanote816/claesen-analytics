<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KNX installation projects. `code` is the natural key the whole contract uses
 * ("C1618"), unique per organization — the API addresses projects by it, not by
 * the numeric id.
 *
 * `lead_name` is deliberately a display string and not a user FK: the contract's
 * own type is `lead: string` ("L. Smet"). Wiring the lead to an office user is a
 * product decision (which user model, what short-name format) that has not been
 * taken yet; every other display field in this module (registered_by_name,
 * reported_by_name, uploaded_by_name, executor_name…) follows the same rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('client_id')->constrained('knx_clients')->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('lead_name')->nullable();
            $table->unsignedInteger('devices_planned')->default(0);
            $table->unsignedInteger('devices_done')->default(0);
            $table->unsignedInteger('photos')->default(0);
            $table->string('status', 20)->default('planned');
            $table->date('deadline')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_projects');
    }
};
