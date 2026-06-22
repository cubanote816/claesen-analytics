<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;

class LuminaireFrameType extends Model
{
    use SoftDeletes;

    protected $table = 'fo_luminaire_frame_types';

    protected $fillable = ['created_by_user_id', 'name', 'image'];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
