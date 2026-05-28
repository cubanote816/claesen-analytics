<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop FK before rename to avoid constraint name conflicts
        Schema::table('prospect_mail_logs', function (Blueprint $table) {
            $table->dropForeign(['prospect_mail_campaign_id']);
        });

        Schema::rename('prospect_mail_logs', 'mailing_messages');

        Schema::table('mailing_messages', function (Blueprint $table) {
            $table->renameColumn('prospect_mail_campaign_id', 'campaign_id');

            // Re-add FK pointing to renamed campaigns table
            $table->foreign('campaign_id')
                ->references('id')->on('mailing_campaigns')
                ->cascadeOnDelete();

            // tracking_token for open/click pixel tracking
            $table->string('tracking_token', 64)->nullable()->unique()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('mailing_messages', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn('tracking_token');
            $table->renameColumn('campaign_id', 'prospect_mail_campaign_id');
        });

        Schema::rename('mailing_messages', 'prospect_mail_logs');

        Schema::table('prospect_mail_logs', function (Blueprint $table) {
            $table->foreign('prospect_mail_campaign_id')
                ->references('id')->on('mailing_campaigns')
                ->cascadeOnDelete();
        });
    }
};
