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
        Schema::table('users', function (Blueprint $table) {
            $table->string('microsoft_id')->nullable()->unique()->after('id');
            $table->text('azure_token')->nullable();
            $table->text('azure_refresh_token')->nullable();
            $table->timestamp('azure_token_expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'microsoft_id',
                'azure_token',
                'azure_refresh_token',
                'azure_token_expires_at',
            ]);
        });
    }
};
