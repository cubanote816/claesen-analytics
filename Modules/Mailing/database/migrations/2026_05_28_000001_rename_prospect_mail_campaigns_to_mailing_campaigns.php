<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('prospect_mail_campaigns', 'mailing_campaigns');

        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->renameColumn('user_id', 'created_by');
            $table->renameColumn('success_count', 'sent_count');
        });
    }

    public function down(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->renameColumn('created_by', 'user_id');
            $table->renameColumn('sent_count', 'success_count');
        });

        Schema::rename('mailing_campaigns', 'prospect_mail_campaigns');
    }
};
