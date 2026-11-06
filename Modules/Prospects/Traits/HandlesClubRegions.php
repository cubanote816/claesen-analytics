<?php

namespace Modules\Prospects\Traits;

use Modules\Prospects\Models\Region;

trait HandlesClubRegions
{
    /**
     * Map a Belgian postal code to a Region ID.
     * 
     * @param string|int|null $zip
     * @return int
     */
    protected function getRegionIdFromPostalCode($zip): int
    {
        return $this->findRegionIdFromPostalCode($zip) ?? $this->getFallbackRegionId();
    }

    /**
     * Map a Belgian postal code to a Region ID, or null when it matches no region.
     *
     * @param string|int|null $zip
     */
    protected function findRegionIdFromPostalCode($zip): ?int
    {
        if (!$zip) {
            return null;
        }

        $code = (int) $zip;
        $regionName = match (true) {
            ($code >= 1000 && $code <= 1299) => 'Brussel',
            ($code >= 1300 && $code <= 1499) => 'Brabant Wallon',
            ($code >= 1500 && $code <= 1999) => 'Vlaams-Brabant',
            ($code >= 2000 && $code <= 2999) => 'Antwerpen',
            ($code >= 3000 && $code <= 3499) => 'Vlaams-Brabant',
            ($code >= 3500 && $code <= 3999) => 'Limburg',
            ($code >= 4000 && $code <= 4999) => 'Liège',
            ($code >= 5000 && $code <= 5999) => 'Namur',
            ($code >= 6000 && $code <= 6599) => 'Hainaut',
            ($code >= 6600 && $code <= 6999) => 'Luxembourg',
            ($code >= 7000 && $code <= 7999) => 'Hainaut',
            ($code >= 8000 && $code <= 8999) => 'West-Vlaanderen',
            ($code >= 9000 && $code <= 9999) => 'Oost-Vlaanderen',
            default => null,
        };

        return $regionName ? Region::where('name', $regionName)->value('id') : null;
    }

    /**
     * Resolve a region by its name (e.g. an RBFA discovery province), or null when unknown.
     */
    protected function findRegionIdByName(?string $name): ?int
    {
        return $name ? Region::where('name', $name)->value('id') : null;
    }

    /**
     * Get the ID of the 'Overige' fallback region.
     * 
     * @return int
     */
    protected function getFallbackRegionId(): int
    {
        return Region::firstOrCreate(
            ['name' => 'Overige'],
            ['slug' => 'overige']
        )->id;
    }
}
