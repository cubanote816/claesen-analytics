<?php

namespace Modules\Safety\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Safety\Database\Factories\QuestionFactory;

class Question extends Model
{
    use HasFactory;

    protected static function newFactory(): QuestionFactory
    {
        return QuestionFactory::new();
    }

    protected $table = 'safety_questions';

    protected $fillable = [
        'checklist_id',
        'text_nl',
        'order',
        'allow_yes',
        'allow_no',
        'allow_na',
    ];

    protected $casts = [
        'allow_yes' => 'boolean',
        'allow_no'  => 'boolean',
        'allow_na'  => 'boolean',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }
}
