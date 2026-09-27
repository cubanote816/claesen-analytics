<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Modules\Prospects\DataSource\BrusselsCadastreSource;
use Modules\Prospects\Exceptions\DataSourceException;
use Tests\TestCase;

class BrusselsCadastreSourceTest extends TestCase
{
    private function fixtureCsv(): string
    {
        return file_get_contents(base_path('Modules/Prospects/tests/fixtures/brussels/infra_sample.csv'));
    }

    private function fakeSuccess(): void
    {
        Http::fake([
            'backend.datastore.brussels/*' => Http::response($this->fixtureCsv(), 200),
        ]);
    }

    public function test_parse_returns_records_from_fixture(): void
    {
        $this->fakeSuccess();

        $clubs = (new BrusselsCadastreSource)->fetchClubs();

        $this->assertCount(3, $clubs);
    }

    public function test_csv_header_typo_is_consumed_literally(): void
    {
        $this->fakeSuccess();

        $clubs = (new BrusselsCadastreSource)->fetchClubs();
        $complex = collect($clubs)->firstWhere('externalId', 'BR-CAD-0aab773e-c697-4ad7-805c-e37de5c0ad58');

        $this->assertNotNull($complex);
        $this->assertStringContainsString('Roosendaelstraat', $complex->venues[0]->address);
    }

    public function test_external_id_uses_br_cad_prefix(): void
    {
        $this->fakeSuccess();

        $clubs = (new BrusselsCadastreSource)->fetchClubs();

        foreach ($clubs as $club) {
            $this->assertStringStartsWith('BR-CAD-', $club->externalId);
        }
    }

    public function test_region_mapping_via_postal_code(): void
    {
        $this->fakeSuccess();

        $clubs = (new BrusselsCadastreSource)->fetchClubs();
        $complex = collect($clubs)->firstWhere('externalId', 'BR-CAD-0048175d-1a1a-480d-949f-6e13d46e307f');

        $this->assertSame('1190', $complex->postalCode);
    }

    public function test_language_from_name_fields(): void
    {
        $this->fakeSuccess();

        $clubs = (new BrusselsCadastreSource)->fetchClubs();
        $byId = collect($clubs)->keyBy('externalId');

        $this->assertSame('nl', $byId->get('BR-CAD-0048175d-1a1a-480d-949f-6e13d46e307f')->language);
        $this->assertSame('fr', $byId->get('BR-CAD-brcad-fixture-lang-only-fr')->language);
    }

    public function test_rows_without_assemblable_address_are_skipped(): void
    {
        $this->fakeSuccess();

        $clubs = (new BrusselsCadastreSource)->fetchClubs();
        $externalIds = collect($clubs)->pluck('externalId')->all();

        $this->assertNotContains('BR-CAD-00c6f855-3a6a-4dd0-8f7c-f1666c56779a', $externalIds);
    }

    public function test_utf8_bom_prefix_does_not_break_the_first_column(): void
    {
        Http::fake([
            'backend.datastore.brussels/*' => Http::response("\xEF\xBB\xBF".$this->fixtureCsv(), 200),
        ]);

        $clubs = (new BrusselsCadastreSource)->fetchClubs();

        $this->assertCount(3, $clubs);
        $this->assertStringStartsWith('BR-CAD-', $clubs[0]->externalId);
    }

    public function test_download_failure_throws_data_source_exception(): void
    {
        Http::fake([
            'backend.datastore.brussels/*' => Http::response('', 500),
        ]);

        $this->expectException(DataSourceException::class);

        (new BrusselsCadastreSource)->fetchClubs();
    }
}
