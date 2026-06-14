<?php

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Model;

class PriceIndex extends Model
{
    protected $table = 'intelligence_price_indices';

    protected $fillable = [
        'category',
        'year',
        'month',
        'index_value',
        'base_year',
        'source',
        'notes',
    ];

    protected $casts = [
        'year'        => 'integer',
        'month'       => 'integer',
        'index_value' => 'float',
        'base_year'   => 'integer',
    ];

    /**
     * Returns the inflation factor between two years for a given category.
     * factor > 1 means costs grew; factor < 1 means deflation.
     */
    public static function factor(string $category, int $fromYear, int $toYear): float
    {
        $from = static::where('category', $category)
            ->where('year', $fromYear)
            ->whereNull('month')
            ->value('index_value');

        $to = static::where('category', $category)
            ->where('year', $toYear)
            ->whereNull('month')
            ->value('index_value');

        if (!$from || !$to) {
            return 1.0;
        }

        return (float) $to / (float) $from;
    }
}
