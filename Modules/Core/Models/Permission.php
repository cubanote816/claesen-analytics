<?php

namespace Modules\Core\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    public function getFormattedNameAttribute(): string
    {
        return \Illuminate\Support\Str::headline($this->name);
    }
}
