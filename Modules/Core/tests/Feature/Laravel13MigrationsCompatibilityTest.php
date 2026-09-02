<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Tests\TestCase;

/**
 * CLA-524 — protege el gap de "migrate desde cero" que no cubre ningun otro
 * Laravel13*CompatibilityTest. El resto de la cadena L13 (auth/CSRF, Modules,
 * Filament/media, cache/queue/mail) ya tiene su propio test de compatibilidad.
 *
 * El riesgo concreto: en Activitylog 5 la clave config('activitylog.table_name')
 * fue eliminada -> resuelve a null -> Schema::create(null) rompe migrate:fresh.
 * CLA-526 lo corrigio con un fallback literal en las 3 migraciones historicas;
 * este test evita que alguien revierta ese fallback.
 */
class Laravel13MigrationsCompatibilityTest extends TestCase
{
    private const ACTIVITY_LOG_MIGRATIONS = [
        'database/migrations/2026_02_09_140342_create_activity_log_table.php',
        'database/migrations/2026_02_09_140343_add_event_column_to_activity_log_table.php',
        'database/migrations/2026_02_09_140344_add_batch_uuid_column_to_activity_log_table.php',
    ];

    public function test_activitylog_config_no_longer_exposes_the_removed_table_name_key(): void
    {
        // Activitylog v5 dropped `table_name` / `database_connection`.
        $this->assertNull(config('activitylog.table_name'));
        $this->assertArrayNotHasKey('table_name', config('activitylog'));
    }

    public function test_the_historical_activity_log_migrations_never_pass_a_null_table_name(): void
    {
        foreach (self::ACTIVITY_LOG_MIGRATIONS as $relativePath) {
            $source = file_get_contents(base_path($relativePath));

            $this->assertStringNotContainsString(
                "config('activitylog.table_name')",
                $source,
                "$relativePath must not depend on the removed activitylog.table_name key without a fallback.",
            );
            $this->assertStringContainsString(
                "config('activitylog.table_name', 'activity_log')",
                $source,
                "$relativePath must fall back to the literal 'activity_log' table name.",
            );
        }
    }

    public function test_the_activitylog_v5_upgrade_migration_is_reversible(): void
    {
        $path = base_path('database/migrations/2026_08_30_100000_upgrade_activity_log_table_to_activitylog_v5.php');
        $this->assertFileExists($path);

        $source = file_get_contents($path);

        // up(): adds attribute_changes, drops batch_uuid.
        $this->assertStringContainsString("\$table->json('attribute_changes')", $source);
        $this->assertStringContainsString("\$table->dropColumn('batch_uuid')", $source);

        // down(): must restore batch_uuid and drop attribute_changes.
        $this->assertStringContainsString("\$table->uuid('batch_uuid')", $source);
        $this->assertStringContainsString("\$table->dropColumn('attribute_changes')", $source);
        $this->assertMatchesRegularExpression('/public function down\(\): void/', $source);
    }
}
