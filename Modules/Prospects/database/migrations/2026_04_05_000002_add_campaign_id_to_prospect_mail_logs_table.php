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
        Schema::table('prospect_mail_logs', function (Blueprint $table) {
            $table->foreignId('prospect_mail_campaign_id')
                ->after('user_id')
                ->nullable()
                ->constrained('prospect_mail_campaigns')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prospect_mail_logs', function (Blueprint $table) {
            $table->dropForeign(['prospect_mail_campaign_id']);
            $table->dropColumn('prospect_mail_campaign_id');
        });
    }
};
