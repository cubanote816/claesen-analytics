<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original rename migration (2026_04_10_221000) did not include
     * analytics_mirror_invoices. This migration corrects that omission:
     * rename if the old table exists, or create fresh if neither exists.
     */
    public function up(): void
    {
        $old = 'analytics_mirror_invoices';
        $new = 'intelligence_mirror_invoices';

        if (Schema::hasTable($old) && !Schema::hasTable($new)) {
            Schema::rename($old, $new);
            return;
        }

        if (!Schema::hasTable($new)) {
            Schema::create($new, function (Blueprint $table) {
                $table->string('id', 20)->primary();
                $table->string('project_id', 15)->index();
                $table->decimal('total_price_vat_excl', 12, 4)->default(0);
                $table->decimal('total_price', 12, 4)->default(0);
                $table->decimal('total_paid', 12, 4)->default(0);
                $table->date('date')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        $old = 'analytics_mirror_invoices';
        $new = 'intelligence_mirror_invoices';

        if (Schema::hasTable($new) && !Schema::hasTable($old)) {
            Schema::rename($new, $old);
        }
    }
};
