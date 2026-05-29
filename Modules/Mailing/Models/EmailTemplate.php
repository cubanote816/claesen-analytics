<?php

namespace Modules\Mailing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\TemplateCategory;

class EmailTemplate extends Model
{
    protected $fillable = [
        'name',
        'subject',
        'body',
        'category',
        'variables',
        'version',
        'parent_id',
        'created_by',
    ];

    protected $casts = [
        'category'  => TemplateCategory::class,
        'variables' => 'array',
        'version'   => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $template) {
            if (empty($template->created_by) && auth()->id()) {
                $template->created_by = auth()->id();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Returns variable keys extracted from variables JSON. */
    public function variableKeys(): array
    {
        return array_column($this->variables ?? [], 'key');
    }
}
