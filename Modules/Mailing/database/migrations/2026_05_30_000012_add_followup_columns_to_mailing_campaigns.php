<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->foreignId('followup_campaign_id')
                  ->nullable()
                  ->constrained('mailing_campaigns')
                  ->nullOnDelete()
                  ->after('ab_test_started_at');

            $table->enum('followup_trigger', ['clicked', 'not_clicked', 'opened', 'not_opened'])
                  ->nullable()
                  ->after('followup_campaign_id');

            $table->smallInteger('followup_delay_hours')
                  ->nullable()
                  ->after('followup_trigger');

            // Atomic claim field. Set to NOW() when processed (even if audience is empty).
            // A null value means "not processed yet".
            $table->dateTime('followup_dispatched_at')
                  ->nullable()
                  ->after('followup_delay_hours');

            $table->index(
                ['status', 'finished_at', 'followup_dispatched_at'],
                'mailing_campaigns_followup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->dropIndex('mailing_campaigns_followup_idx');
            $table->dropForeign(['followup_campaign_id']);
            $table->dropColumn([
                'followup_campaign_id',
                'followup_trigger',
                'followup_delay_hours',
                'followup_dispatched_at',
            ]);
        });
    }
};
