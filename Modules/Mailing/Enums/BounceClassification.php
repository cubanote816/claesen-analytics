<?php

namespace Modules\Mailing\Enums;

enum BounceClassification
{
    case HARD;
    case SOFT;
    case UNKNOWN;
}
