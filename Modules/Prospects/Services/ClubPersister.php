<?php

namespace Modules\Prospects\Services;

use Modules\Prospects\DataObjects\ClubLocation;
use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Support\ContactType;
use Modules\Prospects\Traits\HandlesClubRegions;
use Throwable;

class ClubPersister
{
    use HandlesClubRegions;

    public function persist(NormalizedClub $club): Prospect
    {
        $prospect = Prospect::updateOrCreate(
            ['external_id' => $club->externalId],
            [
                'name' => $club->name,
                'type' => $club->type,
                'federation' => $club->federation,
                'language' => $club->language,
                'website' => $club->website,
                'logo_url' => $club->logoUrl,
                'vat_number' => $club->vatNumber,
                'contact_person' => $club->contactPerson,
                'channel' => $club->channel,
                'region_id' => $this->getRegionIdFromPostalCode($club->postalCode),
            ],
        );

        if ($club->headquarters !== null) {
            $this->persistLocation($prospect, $club->externalId.'::hq', ContactType::Headquarters, $club->headquarters);
        }

        foreach ($club->venues as $index => $venue) {
            $key = $club->externalId.'::'.($venue->sourceLocationId ?? 'v'.($index + 1));
            $this->persistLocation($prospect, $key, ContactType::Venue, $venue);
        }

        return $prospect;
    }

    /**
     * @param  array<int, NormalizedClub>  $clubs
     * @return array{persisted: int, failed: int}
     */
    public function persistAll(array $clubs): array
    {
        $tally = ['persisted' => 0, 'failed' => 0];

        foreach ($clubs as $club) {
            try {
                $this->persist($club);
                $tally['persisted']++;
            } catch (Throwable) {
                $tally['failed']++;
            }
        }

        return $tally;
    }

    private function persistLocation(Prospect $prospect, string $externalId, ContactType $type, ClubLocation $location): void
    {
        ProspectLocation::updateOrCreate(
            ['prospect_id' => $prospect->id, 'external_id' => $externalId],
            [
                'contact_type' => $type->value,
                'contact_name' => $location->contactName,
                'email' => $location->email,
                'phone' => $location->phone,
                'address' => $location->address,
            ],
        );
    }
}
