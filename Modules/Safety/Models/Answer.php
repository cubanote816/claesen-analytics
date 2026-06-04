<?php

namespace Modules\Safety\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Safety\Database\Factories\AnswerFactory;

class Answer extends Model
{
    use HasFactory;

    protected static function newFactory(): AnswerFactory
    {
        return AnswerFactory::new();
    }

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
