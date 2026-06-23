<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\FieldOps\Database\Factories\TerrainTypeFactory;
use Spatie\Translatable\HasTranslations;

class TerrainType extends Model
{
    use HasFactory, SoftDeletes, HasTranslations;

    protected $table = 'fo_terrain_types';

    public array $translatable = ['type'];

    protected $fillable = ['type'];

    protected static function newFactory(): TerrainTypeFactory
    {
        return TerrainTypeFactory::new();
    }
}
