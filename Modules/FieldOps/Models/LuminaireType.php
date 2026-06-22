<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;

class LuminaireType extends Model
{
    protected $table = 'fo_luminaire_types';

    protected $fillable = ['created_by_user_id', 'luminaire_subgroup_id', 'name', 'image'];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function subgroup()
    {
        return $this->belongsTo(LuminaireSubgroup::class, 'luminaire_subgroup_id');
    }
}
