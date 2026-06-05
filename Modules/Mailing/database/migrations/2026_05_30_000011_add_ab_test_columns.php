<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->text('ab_subject_b')->nullable()->after('subject_snapshot');
            $table->tinyInteger('ab_split_percent')->default(10)->after('ab_subject_b');
            $table->tinyInteger('ab_winner_after_hours')->default(4)->after('ab_split_percent');
            $table->char('ab_winner_variant', 1)->nullable()->after('ab_winner_after_hours');
            $table->dateTime('ab_winner_selected_at')->nullable()->after('ab_winner_variant');
            $table->dateTime('ab_test_started_at')->nullable()->after('ab_winner_selected_at');

            $table->index(
                ['status', 'ab_test_started_at', 'ab_winner_selected_at'],
                'mailing_campaigns_ab_idx'
            );
        });

        Schema::table('mailing_messages', function (Blueprint $table) {
            $table->char('ab_variant', 1)->nullable()->after('tracking_token');
            $table->index(['campaign_id', 'ab_variant'], 'mailing_messages_ab_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->dropIndex('mailing_campaigns_ab_idx');
            $table->dropColumn([
                'ab_subject_b', 'ab_split_percent', 'ab_winner_after_hours',
                'ab_winner_variant', 'ab_winner_selected_at', 'ab_test_started_at',
            ]);
        });

        Schema::table('mailing_messages', function (Blueprint $table) {
            // Dropping ab_variant automatically reduces the composite index
            // (campaign_id, ab_variant) to (campaign_id), which keeps the FK satisfied.
            // Explicit dropIndex would fail with MySQL 1553.
            $table->dropColumn('ab_variant');
        });
    }
};
