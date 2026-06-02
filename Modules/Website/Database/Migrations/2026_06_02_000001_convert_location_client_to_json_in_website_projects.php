<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_projects', function (Blueprint $table) {
            $table->json('location_json')->nullable()->after('location');
            $table->json('client_json')->nullable()->after('client');
        });

        // Wrap existing plain-string values as {"nl": "value"} so spatie/translatable can read them
        DB::statement("
            UPDATE website_projects
            SET location_json = JSON_OBJECT('nl', location)
            WHERE location IS NOT NULL AND location != ''
        ");

        DB::statement("
            UPDATE website_projects
            SET client_json = JSON_OBJECT('nl', client)
            WHERE client IS NOT NULL AND client != ''
        ");

        Schema::table('website_projects', function (Blueprint $table) {
            $table->dropColumn(['location', 'client']);
        });

        Schema::table('website_projects', function (Blueprint $table) {
            $table->renameColumn('location_json', 'location');
            $table->renameColumn('client_json', 'client');
        });
    }

    public function down(): void
    {
        Schema::table('website_projects', function (Blueprint $table) {
            $table->string('location_str')->nullable()->after('location');
            $table->string('client_str')->nullable()->after('client');
        });

        DB::statement("
            UPDATE website_projects
            SET location_str = JSON_UNQUOTE(JSON_EXTRACT(location, '$.nl'))
            WHERE location IS NOT NULL
        ");

        DB::statement("
            UPDATE website_projects
            SET client_str = JSON_UNQUOTE(JSON_EXTRACT(client, '$.nl'))
            WHERE client IS NOT NULL
        ");

        Schema::table('website_projects', function (Blueprint $table) {
            $table->dropColumn(['location', 'client']);
        });

        Schema::table('website_projects', function (Blueprint $table) {
            $table->renameColumn('location_str', 'location');
            $table->renameColumn('client_str', 'client');
        });
    }
};
