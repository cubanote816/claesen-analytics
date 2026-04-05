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
        if (!$zip) {
            return $this->getFallbackRegionId();
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

        if ($regionName) {
            $region = Region::where('name', $regionName)->first();
            if ($region) {
                return $region->id;
            }
        }

        return $this->getFallbackRegionId();
    }

    /**
     * Get the ID of the 'Overige' fallback region.
     * 
     * @return int
     */
    protected function getFallbackRegionId(): int
    {
        return Region::firstOrCreate(['name' => 'Overige'])->id;
    }
}
