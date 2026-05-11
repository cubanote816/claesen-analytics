<?php

namespace Modules\Safety\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checklist extends Model
{
    protected $table = 'safety_checklists';

    protected $fillable = [
        'name',
        'is_active',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
