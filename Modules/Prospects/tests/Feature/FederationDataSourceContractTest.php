<?php

namespace Modules\Prospects\Tests\Feature;

use InvalidArgumentException;
use Modules\Prospects\Contracts\FederationDataSource;
use Modules\Prospects\DataObjects\ClubLocation;
use Modules\Prospects\DataObjects\NormalizedClub;
use Tests\TestCase;

class FederationDataSourceContractTest extends TestCase
{
    public function test_every_adapter_implements_interface(): void
    {
        $adapterFiles = glob(base_path('Modules/Prospects/DataSource/*.php')) ?: [];

        foreach ($adapterFiles as $adapterFile) {
            $class = 'Modules\\Prospects\\DataSource\\'.pathinfo($adapterFile, PATHINFO_FILENAME);

            $this->assertTrue(class_exists($class), "Adapter {$class} must be autoloadable.");
            $this->assertTrue(
                is_subclass_of($class, FederationDataSource::class),
                "Adapter {$class} must implement FederationDataSource."
            );
        }

        $this->addToAssertionCount(1);
    }

    public function test_adapter_returns_normalized_club_shape(): void
    {
        $adapter = new class implements FederationDataSource
        {
            public function name(): string
            {
                return 'test';
            }

            public function fetchClubs(): array
            {
                return [NormalizedClub::fromArray([
                    'externalId' => 'VL-TEST-1',
                    'federation' => 'VL-TEST',
                    'name' => 'Test Club',
                    'type' => 'football_club',
                    'language' => 'nl',
                    'postalCode' => '2000',
                    'headquarters' => [
                        'address' => 'Teststraat 1, 2000 Antwerpen',
                        'sourceLocationId' => '1',
                    ],
                ])];
            }
        };

        $club = $adapter->fetchClubs()[0];

        $this->assertInstanceOf(NormalizedClub::class, $club);
        $this->assertSame('VL-TEST-1', $club->externalId);
        $this->assertSame('VL-TEST', $club->federation);
        $this->assertSame('Test Club', $club->name);
        $this->assertSame('football_club', $club->type);
        $this->assertSame('nl', $club->language);
        $this->assertInstanceOf(ClubLocation::class, $club->headquarters);
        $this->assertSame('1', $club->headquarters->sourceLocationId);
    }

    public function test_external_id_prefix_matches_scheme(): void
    {
        foreach (['VL-TEST-1', 'FR-TEST-1', 'ARBH-TEST-1', 'BR-TEST-1'] as $externalId) {
            $club = NormalizedClub::fromArray($this->clubData(['externalId' => $externalId]));

            $this->assertMatchesRegularExpression('/^(VL|FR|ARBH|BR)-/', $club->externalId);
        }
    }

    public function test_from_array_rejects_bad_external_id(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NormalizedClub::fromArray($this->clubData(['externalId' => 'FOO-123']));
    }

    public function test_from_array_rejects_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        NormalizedClub::fromArray($this->clubData(['name' => '']));
    }

    private function clubData(array $overrides = []): array
    {
        return array_replace([
            'externalId' => 'VL-TEST-1',
            'federation' => 'VL-TEST',
            'name' => 'Test Club',
            'type' => 'football_club',
            'language' => 'nl',
            'postalCode' => '2000',
        ], $overrides);
    }
}
