<?php

namespace Modules\Prospects\DataObjects;

use InvalidArgumentException;

final readonly class NormalizedClub
{
    /**
     * @param array<int, ClubLocation> $venues
     */
    public function __construct(
        public string $externalId,
        public string $federation,
        public string $name,
        public string $type,
        public string $language,
        public ?string $postalCode,
        public ?string $website = null,
        public ?string $logoUrl = null,
        public ?string $vatNumber = null,
        public ?string $contactPerson = null,
        public ?string $channel = null,
        public ?ClubLocation $headquarters = null,
        public array $venues = [],
    ) {
    }

    public static function fromArray(array $raw): self
    {
        foreach (['externalId', 'federation', 'name', 'type', 'language'] as $key) {
            if (! isset($raw[$key]) || ! is_string($raw[$key]) || trim($raw[$key]) === '') {
                throw new InvalidArgumentException("{$key} must be a non-empty string.");
            }
        }

        if (preg_match('/^(VL|FR|ARBH|BR)-/', $raw['externalId']) !== 1) {
            throw new InvalidArgumentException('externalId must use a supported region prefix.');
        }

        $venues = $raw['venues'] ?? [];
        if (! is_array($venues)) {
            throw new InvalidArgumentException('venues must be an array.');
        }

        return new self(
            externalId: $raw['externalId'],
            federation: $raw['federation'],
            name: $raw['name'],
            type: $raw['type'],
            language: $raw['language'],
            postalCode: $raw['postalCode'] ?? null,
            website: $raw['website'] ?? null,
            logoUrl: $raw['logoUrl'] ?? null,
            vatNumber: $raw['vatNumber'] ?? null,
            contactPerson: $raw['contactPerson'] ?? null,
            channel: $raw['channel'] ?? null,
            headquarters: self::locationFrom($raw['headquarters'] ?? null),
            venues: array_map(self::locationFrom(...), $venues),
        );
    }

    private static function locationFrom(ClubLocation|array|null $location): ?ClubLocation
    {
        if ($location === null || $location instanceof ClubLocation) {
            return $location;
        }

        if (! isset($location['address']) || ! is_string($location['address']) || trim($location['address']) === '') {
            throw new InvalidArgumentException('Location address must be a non-empty string.');
        }

        return new ClubLocation(
            address: $location['address'],
            contactName: $location['contactName'] ?? null,
            email: $location['email'] ?? null,
            phone: $location['phone'] ?? null,
            sourceLocationId: $location['sourceLocationId'] ?? null,
        );
    }
}
