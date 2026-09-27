<?php

namespace Modules\Prospects\DataObjects;

final readonly class ClubLocation
{
    public function __construct(
        public string $address,
        public ?string $contactName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $sourceLocationId = null,
    ) {
    }
}
