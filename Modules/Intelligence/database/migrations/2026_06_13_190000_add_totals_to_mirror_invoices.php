<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // BI-053 dependency: overdue/partial detection needs the open balance
    // (total_price - total_paid); only total_price_vat_excl was mirrored.

    public function up(): void
    {
        Schema::table('intelligence_mirror_invoices', function (Blueprint $table) {
            $table->decimal('total_price', 14, 4)->default(0)->after('total_price_vat_excl');
            $table->decimal('total_paid', 14, 4)->default(0)->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_mirror_invoices', function (Blueprint $table) {
            $table->dropColumn(['total_price', 'total_paid']);
        });
    }
};
