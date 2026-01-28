<?php

namespace App\Models\Cafca;

use App\Traits\Legacy\ReadOnlyTrait;

class Employee extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'employee';
    protected $primaryKey = 'id';
    protected $keyType = 'int';
    public $incrementing = true;
    public $timestamps = false;

    // Add any necessary accessors or relationships here
}
