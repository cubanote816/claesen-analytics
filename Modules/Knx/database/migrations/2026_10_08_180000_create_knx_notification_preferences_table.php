<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How each person wants to be told (KNX-54).
 *
 * One row per person, created lazily with the defaults the decisions fixed: immediate
 * mail for the three critical notices (`issue_reported`, `malfunction_reported`,
 * `visit_closed` final), and everything else in a digest at visit close — or hourly
 * while the visit is still open.
 *
 * `muted_kinds` silences KINDS in the app inbox; it never silences the immediate mail,
 * which is the safety net the three critical kinds exist for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knx_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('employee_id')->unique()->constrained('knx_employees')->cascadeOnDelete();
            $table->boolean('email_immediate')->default(true);
            // per_visit | hourly | off
            $table->string('email_digest', 16)->default('per_visit');
            $table->json('muted_kinds')->nullable();
            $table->timestamps();
        });

        // When the notice was emailed. Null means "not emailed yet": the immediate kinds
        // set it when the mail goes out, and the digest picks up the rest. It is what
        // makes a field retry (which creates no second row) unable to duplicate a mail.
        Schema::table('knx_notifications', function (Blueprint $table): void {
            $table->timestamp('emailed_at')->nullable()->after('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('knx_notifications', function (Blueprint $table): void {
            $table->dropColumn('emailed_at');
        });

        Schema::dropIfExists('knx_notification_preferences');
    }
};
