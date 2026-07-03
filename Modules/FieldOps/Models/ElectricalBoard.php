<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Factories\ElectricalBoardFactory;
use Modules\Intelligence\Traits\HasAiTranslations;
use Spatie\Translatable\HasTranslations;

class ElectricalBoard extends Model
{
    use HasFactory, SoftDeletes, HasTranslations, HasAiTranslations;

    protected $table = 'fo_electrical_boards';

    public array $translatable = ['location_description'];

    public function getAiTranslatableAttributes(): array
    {
        return ['location_description'];
    }

    protected $fillable = [
        'created_by_user_id',
        'electrical_board_type_id',
        'lat',
        'lng',
        'location_description',
        'ai_translation_status',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    protected static function newFactory(): ElectricalBoardFactory
    {
        return ElectricalBoardFactory::new();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function electricalBoardType()
    {
        return $this->belongsTo(ElectricalBoardType::class);
    }

    public function complexes()
    {
        return $this->belongsToMany(Complex::class, 'fo_complex_electrical_board');
    }

    public function terrains()
    {
        return $this->belongsToMany(Terrain::class, 'fo_electrical_board_terrain');
    }

    public function structures()
    {
        return $this->belongsToMany(Structure::class, 'fo_electrical_board_structure');
    }
}
