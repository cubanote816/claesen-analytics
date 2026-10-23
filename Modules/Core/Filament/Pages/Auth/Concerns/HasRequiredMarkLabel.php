<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Pages\Auth\Concerns;

use Illuminate\Support\HtmlString;

/** Field label whose required asterisk is hidden from assistive technology (shared auth layout). */
trait HasRequiredMarkLabel
{
    private function labelWithRequiredMark(string $label): HtmlString
    {
        return new HtmlString(e($label).' <span class="cafca-login-req" aria-hidden="true">*</span>');
    }
}
