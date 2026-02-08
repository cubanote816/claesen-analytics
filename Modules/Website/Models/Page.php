<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;
use App\Traits\HasAiTranslations;

class Page extends Model
{
    use HasFactory, SoftDeletes, HasTranslations, HasAiTranslations;

    protected $table = 'website_pages';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'meta_description',
        'meta_keywords',
        'published',
        'published_at',
        'order_index',
    ];

    public $translatable = [
        'title',
        'content',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'published' => 'boolean',
        'published_at' => 'datetime',
        'order_index' => 'integer',
    ];

    public function scopePublished($query)
    {
        return $query->where('published', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($page) {
            if (!$page->slug) {
                // Ideally this should support translations logic if slug is translatable, 
                // but usually slug is unique per language or shared. 
                // For simplicity assuming single slug or manual handling.
                // If using 'title' which is array, this might fail without specific logic.
                // Leaving manual slug generation for now or handling via Filament logic.
            }
        });
    }
}
