<?php

namespace Modules\Prospects\Support;

enum ContactType: string
{
    case Headquarters = 'headquarters';
    case Venue = 'venue_name';
    case Primary = 'primary';
}
