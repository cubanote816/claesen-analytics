<?php

namespace Modules\Prospects\Contracts;

use Modules\Prospects\DataObjects\NormalizedClub;
use Modules\Prospects\Exceptions\DataSourceException;

interface FederationDataSource
{
    public function name(): string;

    /**
     * @return array<int, NormalizedClub>
     *
     * @throws DataSourceException
     */
    public function fetchClubs(): array;
}
