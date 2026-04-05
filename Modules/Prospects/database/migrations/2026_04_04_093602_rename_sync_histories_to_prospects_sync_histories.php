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
        Schema::rename('sync_histories', 'prospects_sync_histories');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void 
    {
        Schema::rename('prospects_sync_histories', 'sync_histories');
    }
};
