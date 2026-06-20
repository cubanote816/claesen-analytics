<?php

namespace Modules\Mailing\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\TemplateCategory;

class EmailTemplate extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return \Modules\Mailing\Database\Factories\EmailTemplateFactory::new();
    }

    protected $fillable = [
        'name',
        'subject',
        'body',
        'category',
        'preference_category',
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

        static::saving(function (self $template) {
            // Transactional templates must never carry a preference category
            if ($template->category === TemplateCategory::TRANSACTIONAL) {
                $template->preference_category = null;
            }

            // Validate preference_category against the real config allowlist
            if ($template->preference_category !== null) {
                $validKeys = array_keys(config('mailing.preference_categories', []));
                if (! in_array($template->preference_category, $validKeys, true)) {
                    throw new \InvalidArgumentException(
                        "Invalid preference_category '{$template->preference_category}' for template '{$template->name}'. "
                        . 'Valid values: ' . implode(', ', $validKeys) . '.'
                    );
                }
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
