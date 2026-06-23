<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Factories\StructureFactory;
use Modules\Intelligence\Traits\HasAiTranslations;
use Spatie\Translatable\HasTranslations;

class Structure extends Model
{
    use HasFactory, SoftDeletes, HasTranslations, HasAiTranslations;

    protected $table = 'fo_structures';

    public array $translatable = ['info'];

    protected $fillable = [
        'created_by_user_id',
        'structure_type_id',
        'height',
        'lat',
        'lng',
        'info',
        'external_safety_id',
        'external_access_id',
        'cafca_material_id',
        'ai_translation_status',
    ];

    protected $casts = [
        'lat'    => 'float',
        'lng'    => 'float',
        'height' => 'integer',
    ];

    protected static function newFactory(): StructureFactory
    {
        return StructureFactory::new();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function structureType()
    {
        return $this->belongsTo(StructureType::class);
    }

    public function terrains()
    {
        return $this->belongsToMany(Terrain::class, 'fo_structure_terrain');
    }

    public function luminaireFrames()
    {
        return $this->belongsToMany(LuminaireFrame::class, 'fo_luminaire_frame_structure');
    }
}
