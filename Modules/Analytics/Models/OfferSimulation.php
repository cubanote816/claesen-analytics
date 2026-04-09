<?php

namespace Modules\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

class OfferSimulation extends Model
{
    protected $table = 'analytics_offer_simulations';

    protected $fillable = [
        'simulation_hash',
        'description',
        'category',
        'zipcode',
        'complexity',
        'results',
        'historical_context_ids',
    ];

    protected $casts = [
        'results' => 'array',
        'historical_context_ids' => 'array',
        'complexity' => 'float',
    ];
}
