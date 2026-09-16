<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('prospects_prospects')
            ->whereIn('external_id', ['VL-HOCKEY-CD7MX2C', 'FR-HOCKEY-CD7MX4E'])
            ->whereNull('unsubscribed_at')
            ->update(['unsubscribed_at' => now()]);
    }

    public function down(): void
    {
        DB::table('prospects_prospects')
            ->whereIn('external_id', ['VL-HOCKEY-CD7MX2C', 'FR-HOCKEY-CD7MX4E'])
            ->update(['unsubscribed_at' => null]);
    }
};
