<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-activitylog v4 -> v5 (CLA-526).
 *
 * v5 replaces the batch system and stores tracked model changes in a dedicated
 * `attribute_changes` column instead of nesting them under `properties`:
 *   - add   `attribute_changes` (json, nullable)
 *   - move  `properties->attributes` / `properties->old` into `attribute_changes`
 *   - drop  `batch_uuid`
 *
 * Reversible: down() re-adds `batch_uuid`, folds the change data back into
 * `properties` and drops `attribute_changes`.
 */
return new class extends Migration
{
    private const CHANGE_KEYS = ['attributes', 'old'];

    public function up(): void
    {
        if (! Schema::hasColumn('activity_log', 'attribute_changes')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->json('attribute_changes')->nullable()->after('causer_id');
            });
        }

        DB::table('activity_log')
            ->where(function ($query) {
                $query->whereNotNull('properties->attributes')
                    ->orWhereNotNull('properties->old');
            })
            ->eachById(function ($row) {
                $properties = json_decode($row->properties ?? '', true) ?: [];

                $changes = array_intersect_key($properties, array_flip(self::CHANGE_KEYS));
                $remaining = array_diff_key($properties, array_flip(self::CHANGE_KEYS));

                DB::table('activity_log')->where('id', $row->id)->update([
                    'attribute_changes' => $changes === [] ? null : json_encode($changes),
                    'properties' => $remaining === [] ? null : json_encode($remaining),
                ]);
            });

        if (Schema::hasColumn('activity_log', 'batch_uuid')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->dropColumn('batch_uuid');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('activity_log', 'batch_uuid')) {
            Schema::table('activity_log', function (Blueprint $table) {
                $table->uuid('batch_uuid')->nullable()->after('properties');
            });
        }

        if (Schema::hasColumn('activity_log', 'attribute_changes')) {
            DB::table('activity_log')
                ->whereNotNull('attribute_changes')
                ->eachById(function ($row) {
                    $changes = json_decode($row->attribute_changes ?? '', true) ?: [];
                    $properties = json_decode($row->properties ?? '', true) ?: [];

                    $merged = array_merge($properties, array_intersect_key($changes, array_flip(self::CHANGE_KEYS)));

                    DB::table('activity_log')->where('id', $row->id)->update([
                        'properties' => $merged === [] ? null : json_encode($merged),
                    ]);
                });

            Schema::table('activity_log', function (Blueprint $table) {
                $table->dropColumn('attribute_changes');
            });
        }
    }
};
