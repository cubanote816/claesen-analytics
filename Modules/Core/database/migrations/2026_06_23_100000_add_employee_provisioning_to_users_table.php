<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id')->nullable()->unique()->after('id');
            $table->timestamp('password_set_at')->nullable()->after('password');
            $table->string('activation_code_hash')->nullable()->index()->after('password_set_at');
            $table->timestamp('activation_code_expires_at')->nullable()->after('activation_code_hash');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        // Backfill: existing users with a password are considered fully set up.
        // Uses created_at as a reasonable approximation of when the password was established.
        DB::statement(
            'UPDATE users SET password_set_at = created_at WHERE password IS NOT NULL AND password_set_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['activation_code_hash']);
            $table->dropUnique(['employee_id']);
            $table->dropColumn([
                'employee_id',
                'password_set_at',
                'activation_code_hash',
                'activation_code_expires_at',
            ]);
            $table->string('password')->nullable(false)->change();
        });
    }
};
