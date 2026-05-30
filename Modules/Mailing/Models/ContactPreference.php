<?php

namespace Modules\Mailing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Prospects\Models\Prospect;

class ContactPreference extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Modules\Mailing\Database\Factories\ContactPreferenceFactory::new();
    }

    protected $table = 'mailing_contact_preferences';

    protected $fillable = [
        'prospect_id',
        'category',
        'subscribed',
    ];

    protected $casts = [
        'subscribed' => 'boolean',
    ];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
