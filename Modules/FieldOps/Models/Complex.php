<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Factories\ComplexFactory;

class Complex extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fo_complexes';

    protected $fillable = [
        'created_by_user_id',
        'client_id',
        'name',
        'street',
        'city',
        'zipcode',
        'lat',
        'lng',
        'zoom',
    ];

    protected $casts = [
        'lat'  => 'float',
        'lng'  => 'float',
        'zoom' => 'float',
    ];

    protected static function newFactory(): ComplexFactory
    {
        return ComplexFactory::new();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function client()
    {
        return $this->belongsTo(FoClient::class, 'client_id');
    }

    public function terrains()
    {
        return $this->hasMany(Terrain::class, 'complex_id');
    }

    public function electricalBoards()
    {
        return $this->belongsToMany(ElectricalBoard::class, 'fo_complex_electrical_board');
    }
}
