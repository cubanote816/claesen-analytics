<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('language', 5)->default('nl')->after('is_active');
            $table->string('theme', 10)->default('light')->after('language');
            $table->json('preferences_data')->nullable()->after('theme');

            $table->index('language', 'idx_users_language');
            $table->index('theme', 'idx_users_theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_language');
            $table->dropIndex('idx_users_theme');
            $table->dropColumn(['language', 'theme', 'preferences_data']);
        });
    }
};
