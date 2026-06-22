<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class TerrainType extends Model
{
    use SoftDeletes, HasTranslations;

    protected $table = 'fo_terrain_types';

    public array $translatable = ['type'];

    protected $fillable = ['type'];
}
