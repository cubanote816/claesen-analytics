<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prospects_prospects', function (Blueprint $table) {
            $table->timestamp('unsubscribed_at')->nullable()->after('cafca_relation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospects_prospects', function (Blueprint $table) {
            $table->dropColumn('unsubscribed_at');
        });
    }
};
