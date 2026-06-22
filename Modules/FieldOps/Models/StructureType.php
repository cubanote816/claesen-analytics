<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;
use Spatie\Translatable\HasTranslations;

class StructureType extends Model
{
    use SoftDeletes, HasTranslations;

    protected $table = 'fo_structure_types';

    public array $translatable = ['name'];

    protected $fillable = ['created_by_user_id', 'name'];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
