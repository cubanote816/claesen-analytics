<?php

namespace Modules\Safety\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends Model
{
    protected $table = 'safety_inspections';

    protected $fillable = [
        'user_id',
        'checklist_id',
        'project_id',
        'completed_at',
        'pdf_path',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\User::class);
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
