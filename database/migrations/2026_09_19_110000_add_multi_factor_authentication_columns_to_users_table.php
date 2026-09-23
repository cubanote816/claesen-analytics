<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CLA-464 (ADR D8) — Filament 5's native MFA providers
 * (Filament\Auth\MultiFactor\{App,Email}) each expect a specific column on
 * the user model; these three names are fixed by their own
 * InteractsWith*Authentication traits (Modules\Core\Models\User), not
 * chosen here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('app_authentication_secret')->nullable()->after('remember_token');
            $table->text('app_authentication_recovery_codes')->nullable()->after('app_authentication_secret');
            $table->boolean('has_email_authentication')->default(false)->after('app_authentication_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes', 'has_email_authentication']);
        });
    }
};
