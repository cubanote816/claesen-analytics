<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Factories\TerrainFactory;
use Modules\FieldOps\Traits\HasFieldOpsMedia;
use Modules\Intelligence\Traits\HasAiTranslations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Terrain extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, HasTranslations, HasAiTranslations, InteractsWithMedia, HasFieldOpsMedia {
        HasFieldOpsMedia::registerMediaCollections insteadof InteractsWithMedia;
        HasFieldOpsMedia::registerMediaConversions insteadof InteractsWithMedia;
    }

    protected $table = 'fo_terrains';

    public array $translatable = ['name'];

    public function getAiTranslatableAttributes(): array
    {
        return ['name'];
    }

    protected $fillable = [
        'complex_id',
        'created_by_user_id',
        'terrain_type_id',
        'name',
        'lat',
        'lng',
        'ai_translation_status',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    protected static function newFactory(): TerrainFactory
    {
        return TerrainFactory::new();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function complex()
    {
        return $this->belongsTo(Complex::class);
    }

    public function terrainType()
    {
        return $this->belongsTo(TerrainType::class);
    }

    public function structures()
    {
        return $this->belongsToMany(Structure::class, 'fo_structure_terrain');
    }

    public function electricalBoards()
    {
        return $this->belongsToMany(ElectricalBoard::class, 'fo_electrical_board_terrain');
    }
}
