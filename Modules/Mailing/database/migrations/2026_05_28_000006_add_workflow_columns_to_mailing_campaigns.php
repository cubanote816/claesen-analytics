<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Map legacy status values to the new enum vocabulary
        DB::table('mailing_campaigns')
            ->where('status', 'processing')
            ->update(['status' => 'sending']);

        DB::table('mailing_campaigns')
            ->whereNotIn('status', ['draft', 'review', 'approved', 'sending', 'completed', 'failed', 'cancelled'])
            ->update(['status' => 'draft']);

        // Change status column to a proper ENUM
        DB::statement("
            ALTER TABLE mailing_campaigns
            MODIFY COLUMN status ENUM('draft','review','approved','sending','completed','failed','cancelled')
            NOT NULL DEFAULT 'draft'
        ");

        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->foreignId('approved_by')
                  ->nullable()
                  ->after('status')
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['approved_by', 'approved_at']);
        });

        DB::statement("
            ALTER TABLE mailing_campaigns
            MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'draft'
        ");

        DB::table('mailing_campaigns')
            ->where('status', 'sending')
            ->update(['status' => 'processing']);
    }
};
