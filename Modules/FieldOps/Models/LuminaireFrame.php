<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;

class LuminaireFrame extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return \Modules\FieldOps\Database\Factories\LuminaireFrameFactory::new();
    }

    protected $table = 'fo_luminaire_frames';

    protected $fillable = ['created_by_user_id', 'luminaire_frame_type_id'];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function frameType()
    {
        return $this->belongsTo(LuminaireFrameType::class, 'luminaire_frame_type_id');
    }

    public function luminaires()
    {
        return $this->hasMany(Luminaire::class, 'luminaire_frame_id');
    }

    public function structures()
    {
        return $this->belongsToMany(Structure::class, 'fo_luminaire_frame_structure');
    }
}
