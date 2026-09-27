<?php

namespace Modules\Prospects\Tests\Feature;

use Modules\Prospects\DataSource\AfttPdfSource;
use Modules\Prospects\Exceptions\DataSourceException;
use Tests\TestCase;

class AfttPdfSourceTest extends TestCase
{
    private function fixturePath(string $name = 'annuaire_sample.pdf'): string
    {
        return base_path("Modules/Prospects/tests/fixtures/aftt/{$name}");
    }

    public function test_parse_returns_clubs_from_fixture(): void
    {
        $clubs = (new AfttPdfSource($this->fixturePath()))->fetchClubs();

        $this->assertGreaterThanOrEqual(3, count($clubs));
        foreach ($clubs as $club) {
            $this->assertNotEmpty($club->name);
            $this->assertSame('fr', $club->language);
            $this->assertSame('FR-AFTT', $club->federation);
            $this->assertSame('table_tennis_club', $club->type);
        }
    }

    public function test_parse_extracts_postal_code_from_address(): void
    {
        $clubs = (new AfttPdfSource($this->fixturePath()))->fetchClubs();

        $schaerbeek = collect($clubs)->firstWhere('externalId', 'FR-AFTT-BBW015');

        $this->assertNotNull($schaerbeek);
        $this->assertSame('1030', $schaerbeek->postalCode);
        $this->assertStringContainsString('Schaerbeek', $schaerbeek->headquarters->address);
    }

    public function test_parse_handles_club_with_and_without_email(): void
    {
        $clubs = (new AfttPdfSource($this->fixturePath()))->fetchClubs();
        $byExternalId = collect($clubs)->keyBy('externalId');

        $this->assertSame('cttroyal.alpa@gmail.com', $byExternalId->get('FR-AFTT-BBW015')->headquarters->email);
        $this->assertNull($byExternalId->get('FR-AFTT-H448')->headquarters->email);
    }

    public function test_parse_uses_first_venue_when_club_spans_multiple_pages(): void
    {
        $clubs = (new AfttPdfSource($this->fixturePath()))->fetchClubs();
        $multiVenueClub = collect($clubs)->firstWhere('externalId', 'FR-AFTT-BBW034');

        $this->assertNotNull($multiVenueClub);
        $this->assertStringContainsString('Jette', $multiVenueClub->headquarters->address);
    }

    public function test_garbage_bytes_throws_data_source_exception(): void
    {
        $garbagePath = sys_get_temp_dir().'/aftt-garbage-'.uniqid().'.pdf';
        file_put_contents($garbagePath, 'not a pdf at all');

        $this->expectException(DataSourceException::class);

        try {
            (new AfttPdfSource($garbagePath))->fetchClubs();
        } finally {
            unlink($garbagePath);
        }
    }

    public function test_empty_text_after_parse_throws_data_source_exception(): void
    {
        $this->expectException(DataSourceException::class);

        (new AfttPdfSource($this->fixturePath('blank_no_clubs.pdf')))->fetchClubs();
    }

    public function test_external_id_uses_aftt_club_number(): void
    {
        $clubs = (new AfttPdfSource($this->fixturePath()))->fetchClubs();

        $externalIds = collect($clubs)->pluck('externalId')->all();

        $this->assertContains('FR-AFTT-BBW015', $externalIds);
        $this->assertContains('FR-AFTT-BBW034', $externalIds);
        $this->assertContains('FR-AFTT-H448', $externalIds);
    }
}
