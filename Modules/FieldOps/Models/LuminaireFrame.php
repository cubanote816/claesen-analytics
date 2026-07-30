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
        return $this->hasMany(Luminaire::class, 'luminaire_frame_id')
            ->whereNull('removed_at')
            ->whereNotNull('active_position_id');
    }

    public function luminairePositions()
    {
        return $this->hasMany(LuminairePosition::class, 'luminaire_frame_id');
    }

    public function structures()
    {
        return $this->belongsToMany(Structure::class, 'fo_luminaire_frame_structure');
    }

    // Same M:N-in-real-data situation as Structure::resolveTerrain() (frames with 2+
    // structures exist, up to Structure::MAX_LUMINAIRE_FRAMES) — no single "correct"
    // parent structure. $viaStructureId prefers the structure the user actually
    // navigated through; falls back to the lowest-id structure otherwise.
    public function resolveStructure(?int $viaStructureId = null): ?Structure
    {
        if ($viaStructureId) {
            $structure = $this->structures()->find($viaStructureId);

            if ($structure) {
                return $structure;
            }
        }

        return $this->structures()->orderBy('fo_structures.id')->first();
    }
}
