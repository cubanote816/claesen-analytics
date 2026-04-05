<?php

namespace Modules\Prospects\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectLocation extends Model
{
    use HasFactory;
 
    protected $table = 'prospects_locations';


    protected $fillable = [
        'prospect_id',
        'contact_type',
        'email',
        'phone',
        'address',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
