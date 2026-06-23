<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\FieldOps\Database\Factories\FoClientFactory;

class FoClient extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fo_clients';

    protected $fillable = [
        'name', 'city', 'street', 'phone', 'email', 'language',
    ];

    protected static function newFactory(): FoClientFactory
    {
        return FoClientFactory::new();
    }

    public function complexes()
    {
        return $this->hasMany(Complex::class, 'client_id');
    }
}
