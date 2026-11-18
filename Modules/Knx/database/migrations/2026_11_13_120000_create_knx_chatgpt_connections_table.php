<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An employee's own "Sign in with ChatGPT" connection (one per employee and organization).
 *
 * Identity (the verified `sub`, email, name) is kept even when the tokens are lost: a
 * connection that `needs_sign_in` still says whose it was. The raw ID token is never
 * stored. Access and refresh tokens are encrypted by the model cast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_chatgpt_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('employee_id')->constrained('knx_employees')->cascadeOnDelete();
            $table->string('subject', 191);
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('client_id', 191);
            $table->json('scopes');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('access_expires_at')->nullable();
            $table->boolean('plan_permission')->default(false);
            $table->string('status', 24)->default('connected');
            $table->timestamps();

            $table->unique(['organization_id', 'employee_id'], 'knx_chatgpt_conn_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knx_chatgpt_connections');
    }
};
