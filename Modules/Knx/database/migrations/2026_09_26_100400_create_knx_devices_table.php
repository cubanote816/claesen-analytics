<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registered devices (physical address + room + board + serial).
 *
 * NOTE: there is deliberately NO unique(project_id, address). A duplicated
 * address is not a data error here — it IS the conflict the office resolves in
 * the Conflictencentrum (docs/BACKEND-API.md §5, "Direcciones duplicadas: no
 * bloquear a nivel de BD"). The index is only for lookups.
 *
 * `source` distinguishes what the ETS plan says ('ets') from what was actually
 * registered on site ('field'); `acknowledged_at` is when the office confirmed
 * a field registration, which is what drives `Device.isNew`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('knx_projects')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('knx_project_rooms')->nullOnDelete();
            $table->foreignId('board_id')->nullable()->constrained('knx_boards')->nullOnDelete();
            $table->string('type');
            $table->string('address', 32);
            $table->string('serial')->nullable();
            $table->string('source', 10)->default('ets');
            $table->foreignId('registered_by_employee_id')->nullable()->constrained('knx_employees')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'address']);
            $table->index(['project_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_devices');
    }
};
