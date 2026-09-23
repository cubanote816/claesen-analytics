<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('prospects_prospects')
            ->whereIn('external_id', ['FR-AFT-tc-de-wavre', 'FR-AFT-royal-leopold-club'])
            ->whereNull('unsubscribed_at')
            ->update(['unsubscribed_at' => now()]);
    }

    public function down(): void
    {
        DB::table('prospects_prospects')
            ->whereIn('external_id', ['FR-AFT-tc-de-wavre', 'FR-AFT-royal-leopold-club'])
            ->update(['unsubscribed_at' => null]);
    }
};
