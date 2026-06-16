<?php

namespace Modules\Safety\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\User;
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
        'category',
        'order',
        'allow_yes',
        'allow_no',
        'allow_na',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'allow_yes' => 'boolean',
        'allow_no'  => 'boolean',
        'allow_na'  => 'boolean',
    ];

    protected $hidden = [
        'created_by_user_id',
        'updated_by_user_id',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
