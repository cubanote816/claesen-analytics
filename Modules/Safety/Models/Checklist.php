<?php

namespace Modules\Safety\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Safety\Database\Factories\ChecklistFactory;

class Checklist extends Model
{
    use HasFactory;

    protected static function newFactory(): ChecklistFactory
    {
        return ChecklistFactory::new();
    }

    protected $table = 'safety_checklists';

    protected $fillable = [
        'name',
        'type',
        'is_active',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }
}
