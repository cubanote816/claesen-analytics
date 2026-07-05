<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('intelligence_mirror_relations', function (Blueprint $table) {
            $table->string('street')->nullable()->after('city');
            $table->boolean('tp_customer')->default(false)->after('contact_name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('intelligence_mirror_relations', function (Blueprint $table) {
            $table->dropColumn(['street', 'tp_customer']);
        });
    }
};
