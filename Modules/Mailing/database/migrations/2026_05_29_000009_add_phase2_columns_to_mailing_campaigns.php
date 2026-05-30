<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->enum('audience_type', ['all_subscribed', 'segment', 'manual'])
                  ->default('all_subscribed')
                  ->after('template_id');

            $table->json('audience_filters')->nullable()->after('audience_type');

            $table->dateTime('scheduled_at')->nullable()->after('audience_filters');

            $table->string('timezone', 50)->default('Europe/Brussels')->after('scheduled_at');

            $table->index(['status', 'scheduled_at'], 'mailing_campaigns_status_scheduled_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->dropIndex('mailing_campaigns_status_scheduled_idx');
            $table->dropColumn(['audience_type', 'audience_filters', 'scheduled_at', 'timezone']);
        });
    }
};
