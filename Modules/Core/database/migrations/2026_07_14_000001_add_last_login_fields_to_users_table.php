<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('last_active_at');
            }

            if (! Schema::hasColumn('users', 'last_login_app_source')) {
                $table->string('last_login_app_source')->nullable()->after('last_login_at');
            }

            if (! Schema::hasColumn('users', 'last_login_channel')) {
                $table->string('last_login_channel', 80)->nullable()->after('last_login_app_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'last_login_channel')) {
                $table->dropColumn('last_login_channel');
            }

            if (Schema::hasColumn('users', 'last_login_app_source')) {
                $table->dropColumn('last_login_app_source');
            }

            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
        });
    }
};
