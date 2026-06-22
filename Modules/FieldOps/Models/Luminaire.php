<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;
use Spatie\Translatable\HasTranslations;

class Luminaire extends Model
{
    use SoftDeletes, HasTranslations;

    protected $table = 'fo_luminaires';

    public array $translatable = ['info'];

    protected $fillable = [
        'created_by_user_id',
        'luminaire_type_id',
        'luminaire_subgroup_id',
        'luminaire_frame_id',
        'frame_position',
        'serial_number',
        'frame_x',
        'frame_y',
        'info',
        'cafca_material_id',
    ];

    protected $casts = [
        'frame_x'        => 'float',
        'frame_y'        => 'float',
        'frame_position' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if ($model->frame_position === null) {
                $max = static::where('luminaire_frame_id', $model->luminaire_frame_id)->max('frame_position');
                $model->frame_position = $max ? $max + 1 : 1;
            }
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function luminaireFrame()
    {
        return $this->belongsTo(LuminaireFrame::class, 'luminaire_frame_id');
    }

    public function luminaireType()
    {
        return $this->belongsTo(LuminaireType::class);
    }

    public function subgroup()
    {
        return $this->belongsTo(LuminaireSubgroup::class, 'luminaire_subgroup_id');
    }
}
