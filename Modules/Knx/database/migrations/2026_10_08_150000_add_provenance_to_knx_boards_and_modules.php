<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who wrote a board or a module, and whether the office has reviewed it (KNX-52).
 *
 * A re-import must not silently overwrite what a person edited. `origin` says where the
 * row came from (`import` from the worklist, `office` edited by hand, `field` created
 * from site) and `review_state` says whether the office accepted it (`unreviewed` is
 * what a field creation is born as). With those two facts the importer can skip what a
 * person owns instead of guessing.
 *
 * Existing rows default to `import` + `confirmed`: they all came from the worklist and
 * the office has been working with them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knx_boards', function (Blueprint $table): void {
            $table->string('origin', 10)->default('import')->after('floor');
            $table->string('review_state', 12)->default('confirmed')->after('origin');
            $table->foreignId('updated_by_employee_id')->nullable()->after('review_state')
                ->constrained('knx_employees')->nullOnDelete();
        });

        Schema::table('knx_board_modules', function (Blueprint $table): void {
            $table->string('origin', 10)->default('import')->after('slot');
            $table->string('review_state', 12)->default('confirmed')->after('origin');
            $table->foreignId('updated_by_employee_id')->nullable()->after('review_state')
                ->constrained('knx_employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('knx_boards', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by_employee_id');
            $table->dropColumn(['origin', 'review_state']);
        });

        Schema::table('knx_board_modules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by_employee_id');
            $table->dropColumn(['origin', 'review_state']);
        });
    }
};
