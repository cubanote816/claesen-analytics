<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\FieldOps\Database\Factories\TerrainTypeFactory;
use Modules\Intelligence\Traits\HasAiTranslations;
use Spatie\Translatable\HasTranslations;

class TerrainType extends Model
{
    use HasFactory, SoftDeletes, HasTranslations, HasAiTranslations;

    protected $table = 'fo_terrain_types';

    public array $translatable = ['type'];

    protected $fillable = ['type', 'ai_translation_status'];

    protected static function newFactory(): TerrainTypeFactory
    {
        return TerrainTypeFactory::new();
    }
}
