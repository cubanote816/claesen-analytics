<?php

namespace Modules\Prospects\DataSource;

use Illuminate\Support\Facades\Http;
use Modules\Prospects\Contracts\FederationDataSource;
use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\Exceptions\DataSourceException;
use Smalot\PdfParser\Parser;
use Throwable;

class AfttPdfSource implements FederationDataSource
{
    public const URL = 'https://ep.aftt.be/assets/media/documents/annuaire/annuaire_complet.pdf';

    public function __construct(
        private string $source = self::URL,
        private ?Parser $parser = null,
    ) {
    }

    public function name(): string
    {
        return 'FR-AFTT';
    }

    /** @return array<int, NormalizedClub> */
    public function fetchClubs(): array
    {
        $text = $this->extractText();

        $clubs = $this->parseAnnuaireText($text);

        if ($clubs === []) {
            throw new DataSourceException('No AFTT clubs could be parsed from the annuaire.');
        }

        return $clubs;
    }

    private function extractText(): string
    {
        $bytes = str_starts_with($this->source, 'http')
            ? $this->download($this->source)
            : file_get_contents($this->source);

        if ($bytes === false || $bytes === '') {
            throw new DataSourceException('AFTT annuaire source is empty or unreadable.');
        }

        try {
            $pdf = ($this->parser ?? new Parser)->parseContent($bytes);
        } catch (Throwable $exception) {
            throw new DataSourceException('AFTT annuaire could not be parsed: '.$exception->getMessage(), 0, $exception);
        }

        $text = implode("\n", array_map(
            static fn ($page) => $page->getText(),
            $pdf->getPages(),
        ));

        if (trim($text) === '') {
            throw new DataSourceException('AFTT annuaire produced no extractable text.');
        }

        return $text;
    }

    private function download(string $url): string
    {
        $response = Http::timeout(120)->retry(2, 5000)->get($url);

        if (! $response->successful()) {
            throw new DataSourceException("Failed to download AFTT annuaire: HTTP {$response->status()}.");
        }

        return $response->body();
    }

    /** @return array<int, NormalizedClub> */
    private function parseAnnuaireText(string $text): array
    {
        $pattern = '/^([A-Z]{1,4}\d{2,4}) - (.+?)\R+Informations générales\R+(.*?)(?=^[A-Z]{1,4}\d{2,4} - .+?\R+Informations générales|\z)/ms';

        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        $clubs = [];
        foreach ($matches as $match) {
            $clubNumber = trim($match[1]);
            $name = trim($match[2]);
            $body = $match[3];

            $email = $this->extractField($body, 'Email');
            $address = $this->extractFirstAddress($body);

            $clubs[] = NormalizedClub::fromArray([
                'externalId' => 'FR-AFTT-'.$clubNumber,
                'federation' => 'FR-AFTT',
                'name' => $name,
                'type' => 'table_tennis_club',
                'language' => 'fr',
                'postalCode' => $address['postalCode'] ?? null,
                'headquarters' => [
                    'address' => $address['address'] ?? $name,
                    'email' => $email,
                ],
            ]);
        }

        return $clubs;
    }

    private function extractField(string $body, string $label): ?string
    {
        if (! preg_match('/^'.preg_quote($label, '/').'[ \t]*:[ \t]*(.*)$/m', $body, $match)) {
            return null;
        }

        $value = trim($match[1]);

        return $value !== '' ? $value : null;
    }

    /** @return array{address: ?string, postalCode: ?string} */
    private function extractFirstAddress(string $body): array
    {
        if (! preg_match('/Adresse[ \t]*:[ \t]*(.+?),\s*(\d{4})\s*-\s*(.+)/', $body, $match)) {
            return ['address' => null, 'postalCode' => null];
        }

        $street = trim($match[1]);
        $postalCode = trim($match[2]);
        $city = trim($match[3]);

        return [
            'address' => "{$street}, {$postalCode} - {$city}",
            'postalCode' => $postalCode,
        ];
    }
}
