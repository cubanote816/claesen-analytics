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
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'avatar_path')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('avatar_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // create_employees_table.down() runs first in reverse order and drops the table,
        // so employees may not exist here — guard to avoid a fatal error.
        if (Schema::hasTable('employees') && !Schema::hasColumn('employees', 'avatar_path')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('avatar_path')->nullable();
            });
        }
    }
};
