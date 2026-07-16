<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fo_luminaires', function (Blueprint $table) {
            $table->unsignedInteger('position_version')->default(1)->after('scale_y');
            $table->string('position_source', 32)->nullable()->after('position_version');
            $table->foreignId('position_verified_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('position_source');
            $table->timestamp('position_verified_at')->nullable()->after('position_verified_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('fo_luminaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_verified_by_user_id');
            $table->dropColumn([
                'position_version',
                'position_source',
                'position_verified_at',
            ]);
        });
    }
};
