<?php

declare(strict_types=1);

namespace Modules\Analytics\Enums;

// One catalog entry per consumer app. Adding a new internal app means adding
// a case here — never a free-text string on the ingestion payload.
enum AppSource: string
{
    case BACKOFFICE = 'backoffice';
    case SAFETY_PWA = 'safety_pwa';
    case FIELDOPS_SPORT = 'fieldops_sport';
}
