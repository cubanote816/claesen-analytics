<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_mirror_labor', function (Blueprint $table) {
            $table->string('labor_descr')->nullable()->after('labor_id');
            $table->time('h_from_1')->nullable()->after('labor_descr');
            $table->time('h_to_1')->nullable()->after('h_from_1');
            $table->time('h_from_2')->nullable()->after('h_to_1');
            $table->time('h_to_2')->nullable()->after('h_from_2');
            $table->float('distance')->nullable()->after('h_to_2');
            $table->boolean('fl_approved')->nullable()->after('distance');
            $table->float('total_costprice')->nullable()->after('fl_approved');
            $table->float('total_salesprice')->nullable()->after('total_costprice');
            $table->float('pauze')->nullable()->after('total_salesprice');
            $table->boolean('fl_pauze')->nullable()->after('pauze');
            $table->float('productivity')->nullable()->after('fl_pauze');
            $table->float('transport_costprice')->nullable()->after('productivity');
            $table->float('transport_salesprice')->nullable()->after('transport_costprice');
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_mirror_labor', function (Blueprint $table) {
            $table->dropColumn([
                'labor_descr',
                'h_from_1', 'h_to_1', 'h_from_2', 'h_to_2',
                'distance',
                'fl_approved',
                'total_costprice', 'total_salesprice',
                'pauze', 'fl_pauze',
                'productivity',
                'transport_costprice', 'transport_salesprice',
            ]);
        });
    }
};
