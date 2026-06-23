<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Factories\TerrainFactory;
use Spatie\Translatable\HasTranslations;

class Terrain extends Model
{
    use HasFactory, SoftDeletes, HasTranslations;

    protected $table = 'fo_terrains';

    public array $translatable = ['name'];

    protected $fillable = [
        'complex_id',
        'created_by_user_id',
        'terrain_type_id',
        'name',
        'lat',
        'lng',
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
}
