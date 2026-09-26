<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The eight readiness checks of a zone. Every zone is created with all eight in
 * `pending` (the API guarantees "always the 8 keys, in enum order").
 *
 * `updated_at` is the domain timestamp the contract exposes (`ZoneCheck
 * .updatedAt`, nullable until someone touches the check), so this table
 * deliberately does not use Laravel's created_at/updated_at pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_zone_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained('knx_zones')->cascadeOnDelete();
            $table->string('key', 30);
            $table->string('status', 12)->default('pending');
            $table->text('note')->nullable();
            $table->string('updated_by_name')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['zone_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_zone_checks');
    }
};
