<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function getFormattedNameAttribute(): string
    {
        return \Illuminate\Support\Str::headline($this->name);
    }
}
