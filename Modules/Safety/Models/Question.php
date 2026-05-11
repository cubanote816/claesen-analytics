<?php

namespace Modules\Safety\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    protected $table = 'safety_questions';

    protected $fillable = [
        'checklist_id',
        'text_nl',
        'order',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }
}
