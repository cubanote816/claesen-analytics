<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;

class LuminaireSubgroup extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Modules\FieldOps\Database\Factories\LuminaireSubgroupFactory::new();
    }

    protected $table = 'fo_luminaire_subgroups';

    protected $fillable = ['created_by_user_id', 'group_name', 'brand'];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function luminaireTypes()
    {
        return $this->hasMany(LuminaireType::class, 'luminaire_subgroup_id');
    }

    public function luminaires()
    {
        return $this->hasMany(Luminaire::class, 'luminaire_subgroup_id');
    }
}
