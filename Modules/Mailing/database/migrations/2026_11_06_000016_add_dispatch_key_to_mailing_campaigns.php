<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->string('dispatch_key', 64)->nullable()->unique();
            $table->unsignedInteger('run_generation')->default(0);
            $table->string('run_owner', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table) {
            $table->dropUnique(['dispatch_key']);
            $table->dropColumn(['dispatch_key', 'run_generation', 'run_owner']);
        });
    }
};
