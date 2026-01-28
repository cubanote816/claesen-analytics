<?php

namespace App\Models\Cafca;

use Illuminate\Database\Eloquent\Model;

abstract class CafcaModel extends Model
{
    protected $connection = 'sqlsrv';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false; // Legacy tables usually don't have standard timestamps

    /**
     * Boot the model.
     * Enforces trimming of string attributes to handle SQL Server CHAR padding.
     */
    protected static function boot()
    {
        parent::boot();

        static::retrieved(function ($model) {
            foreach ($model->attributes as $key => $value) {
                if (is_string($value)) {
                    $model->attributes[$key] = trim($value);
                }
            }
        });
    }
}
