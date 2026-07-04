<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Factories\StructureFactory;
use Modules\FieldOps\Traits\HasFieldOpsMedia;
use Modules\Intelligence\Traits\HasAiTranslations;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Translatable\HasTranslations;

class Structure extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, HasTranslations, HasAiTranslations, InteractsWithMedia, HasFieldOpsMedia {
        HasFieldOpsMedia::registerMediaCollections insteadof InteractsWithMedia;
        HasFieldOpsMedia::registerMediaConversions insteadof InteractsWithMedia;
    }

    protected $table = 'fo_structures';

    public array $translatable = ['info'];

    public function getAiTranslatableAttributes(): array
    {
        return ['info'];
    }

    protected $fillable = [
        'created_by_user_id',
        'structure_type_id',
        'height',
        'lat',
        'lng',
        'info',
        'access_type_id',
        'access_active',
        'safety_type_id',
        'safety_certified',
        'cafca_material_id',
        'ai_translation_status',
    ];

    protected $casts = [
        'lat'              => 'float',
        'lng'              => 'float',
        'height'           => 'integer',
        'access_active'    => 'boolean',
        'safety_certified' => 'boolean',
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

    public function accessType()
    {
        return $this->belongsTo(AccessType::class);
    }

    public function safetyType()
    {
        return $this->belongsTo(SafetyType::class);
    }

    public function terrains()
    {
        return $this->belongsToMany(Terrain::class, 'fo_structure_terrain');
    }

    public function luminaireFrames()
    {
        return $this->belongsToMany(LuminaireFrame::class, 'fo_luminaire_frame_structure');
    }

    public function electricalBoards()
    {
        return $this->belongsToMany(ElectricalBoard::class, 'fo_electrical_board_structure');
    }
}
