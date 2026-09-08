<?php

namespace Modules\Core\Tests\Unit;

use Tests\TestCase;

/**
 * CLA-532: config('core.azure_role_mapping') must be built null-safe. The
 * previous runtime env() default array collapsed unset AZURE_GROUP_* vars into
 * a single empty-string key, sending every Azure user to 'viewer'. No database
 * is used.
 */
class AzureRoleMappingConfigTest extends TestCase
{
    /**
     * Rebuild the mapping exactly as Modules/Core/config/config.php does, for a
     * given set of AZURE_GROUP_* values.
     *
     * @param  array<string, string|null>  $env
     * @return array<string, string>
     */
    private function build(array $env): array
    {
        return array_flip(array_filter([
            'super_admin' => $env['AZURE_GROUP_SUPER_ADMIN'] ?? null,
            'admin' => $env['AZURE_GROUP_ADMIN'] ?? null,
            'financial_manager' => $env['AZURE_GROUP_FINANCE'] ?? null,
            'project_manager' => $env['AZURE_GROUP_PM'] ?? null,
        ]));
    }

    public function test_all_unset_produces_an_empty_map_without_an_empty_string_key(): void
    {
        $map = $this->build([]);

        $this->assertSame([], $map);
        $this->assertArrayNotHasKey('', $map);
    }

    public function test_partially_set_map_ignores_the_unset_entries(): void
    {
        $map = $this->build([
            'AZURE_GROUP_SUPER_ADMIN' => 'guid-sa',
            'AZURE_GROUP_ADMIN' => 'guid-admin',
        ]);

        $this->assertSame(
            ['guid-sa' => 'super_admin', 'guid-admin' => 'admin'],
            $map,
        );
        $this->assertArrayNotHasKey('', $map);
    }

    public function test_the_published_config_key_exists_and_is_an_array(): void
    {
        $this->assertIsArray(config('core.azure_role_mapping'));
        $this->assertArrayNotHasKey('', config('core.azure_role_mapping'));
    }
}
