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
            if (!Schema::hasColumn('website_projects', 'location_json')) {
                $table->json('location_json')->nullable()->after('location');
            }
            if (!Schema::hasColumn('website_projects', 'client_json')) {
                $table->json('client_json')->nullable()->after('client');
            }
        });

        // Preserve existing JSON objects; wrap plain strings as {"nl": "value"}
        if (Schema::hasColumn('website_projects', 'location') && Schema::hasColumn('website_projects', 'location_json')) {
            DB::statement("
                UPDATE website_projects
                SET location_json = CASE
                    WHEN JSON_VALID(location) THEN location
                    ELSE JSON_OBJECT('nl', location)
                END
                WHERE location IS NOT NULL AND location != ''
            ");
        }

        if (Schema::hasColumn('website_projects', 'client') && Schema::hasColumn('website_projects', 'client_json')) {
            DB::statement("
                UPDATE website_projects
                SET client_json = CASE
                    WHEN JSON_VALID(client) THEN client
                    ELSE JSON_OBJECT('nl', client)
                END
                WHERE client IS NOT NULL AND client != ''
            ");
        }

        if (Schema::hasColumn('website_projects', 'location') && Schema::hasColumn('website_projects', 'location_json')) {
            Schema::table('website_projects', function (Blueprint $table) {
                $table->dropColumn(['location', 'client']);
            });

            Schema::table('website_projects', function (Blueprint $table) {
                $table->renameColumn('location_json', 'location');
                $table->renameColumn('client_json', 'client');
            });
        }
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
