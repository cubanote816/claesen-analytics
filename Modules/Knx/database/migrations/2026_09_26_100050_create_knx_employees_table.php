<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The people of the KNX domain — office (Kantoor) and field (Veld) alike.
 *
 * Why a local table and not the ERP: Claesen's people come from
 * Modules\Cafca\Models\Employee (SQL Server, read-only), but Electro Bertels
 * has no ERP, and its staff must not be provisioned through Claesen's
 * employee-driven user flow (this is the gap CLA-599 left explicitly open:
 * "el personal de Bertels no tiene Employee"). So Bertels gets its own person
 * table, and `user_id` links it to the account (Modules\Core\Models\User) when
 * that person can actually log in.
 *
 * `kind` is what the two apps see:
 *   - office → Kantoor: project leads, planners, document authors ("L. Smet")
 *   - field  → Veld: the technicians the planning assigns work to ("J. Van Dyck")
 * The same person can only be one of them for now; if someone ever needs both,
 * that is a product decision, not a schema accident.
 *
 * `knx_role` is the *business* role the contract exposes as `Session.role`
 * ("lead"), not an app permission. App access is the Spatie role
 * (knx_office / knx_field) — deliberately NOT a Filament panel role: Kantoor
 * and Veld are their own apps, so these users get no panel access at all
 * (hasPanelAccess() == false → /auth/no-access).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('initials', 8);
            $table->string('kind', 10);
            $table->string('knx_role', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_employees');
    }
};
