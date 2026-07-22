<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;

class LuminairePosition extends Model
{
    protected $table = 'fo_luminaire_positions';

    protected $fillable = [
        'luminaire_frame_id',
        'frame_position',
        'frame_x',
        'frame_y',
        'scale_x',
        'scale_y',
        'position_version',
        'position_source',
        'position_verified_by_user_id',
        'position_verified_at',
    ];

    protected $casts = [
        'frame_position' => 'integer',
        'frame_x' => 'float',
        'frame_y' => 'float',
        'scale_x' => 'float',
        'scale_y' => 'float',
        'position_version' => 'integer',
        'position_verified_at' => 'datetime',
    ];

    public function luminaireFrame()
    {
        return $this->belongsTo(LuminaireFrame::class, 'luminaire_frame_id');
    }

    public function installations()
    {
        return $this->hasMany(Luminaire::class, 'luminaire_position_id');
    }

    public function currentInstallation()
    {
        return $this->hasOne(Luminaire::class, 'active_position_id');
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(FoMaintenanceRecord::class, 'luminaire_position_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'position_verified_by_user_id');
    }
}
