<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FoClient extends Model
{
    use SoftDeletes;

    protected $table = 'fo_clients';

    protected $fillable = [
        'name', 'city', 'street', 'phone', 'email', 'language',
    ];

    public function complexes()
    {
        return $this->hasMany(Complex::class, 'client_id');
    }
}
