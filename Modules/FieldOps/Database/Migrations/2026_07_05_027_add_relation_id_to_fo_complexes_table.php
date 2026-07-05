<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Soft reference to intelligence_mirror_relation_deliveries (relation_id,
     * seq_nr) — CAFCA's composite natural key for a client's site addresses.
     * No FK, validated in app layer, same pattern as fo_clients.relation_id.
     */
    public function up(): void
    {
        Schema::table('fo_complexes', function (Blueprint $table) {
            $table->unsignedInteger('relation_id')->nullable()->after('id');
            $table->unsignedSmallInteger('delivery_seq_nr')->nullable()->after('relation_id');
            $table->unique(['relation_id', 'delivery_seq_nr']);
        });
    }

    public function down(): void
    {
        Schema::table('fo_complexes', function (Blueprint $table) {
            $table->dropUnique(['relation_id', 'delivery_seq_nr']);
            $table->dropColumn(['relation_id', 'delivery_seq_nr']);
        });
    }
};
