<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirror of CAFCA's relation_delivery table — per-client delivery/site
     * addresses. For lighting-contractor clients these are real physical
     * installations (sports halls, fields, stadiums), not just invoice
     * addresses — FieldOps imports FoComplex from this table.
     */
    public function up(): void
    {
        Schema::create('intelligence_mirror_relation_deliveries', function (Blueprint $table) {
            $table->id();

            // Composite natural key from SQL Server (relation_id, seq_nr).
            $table->unsignedInteger('relation_id')->index();
            $table->unsignedSmallInteger('seq_nr');
            $table->unique(['relation_id', 'seq_nr'], 'mirror_relation_deliveries_relation_seq_unique');

            $table->string('name')->nullable();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('zipcode', 15)->nullable();
            $table->boolean('fl_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_mirror_relation_deliveries');
    }
};
