<?php

namespace Modules\Safety\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Answer extends Model
{
    protected $table = 'safety_answers';

    protected $fillable = [
        'inspection_id',
        'question_id',
        'status',
        'remark',
        'photo_path',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
