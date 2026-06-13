<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Notes from SQL Server inspection:
    //   fl_invoice=1 on 75% of rows → type flag (billable), NOT "already invoiced"
    //   fl_paid=1 on only 1 row → not reliable as payment signal yet
    //   fl_needinvoiced → DISCARDED (only 9 rows, unreliable per sprint plan)
    //   total_price/total_paid never NULL in SQL Server (can be 0.0)
    //   status range 0-13; labels managed via bi_config (BI-017)

    public function up(): void
    {
        Schema::create('intelligence_mirror_workdocs', function (Blueprint $table) {
            $table->string('id', 20)->primary();   // WO2019XXXX etc.
            $table->string('project_id', 20)->nullable()->index();
            $table->integer('relation_id')->nullable();
            $table->string('name')->nullable();
            $table->date('date')->nullable();
            $table->smallInteger('status')->nullable();
            $table->boolean('fl_invoice')->default(false);   // billable type flag, NOT invoicing status
            $table->boolean('fl_finished')->default(false);
            $table->boolean('fl_paid')->default(false);
            $table->decimal('total_price', 14, 4)->default(0);
            $table->decimal('total_paid', 14, 4)->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intelligence_mirror_workdocs');
    }
};
