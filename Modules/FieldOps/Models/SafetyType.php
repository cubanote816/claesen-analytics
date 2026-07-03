<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Factories\SafetyTypeFactory;
use Modules\Intelligence\Traits\HasAiTranslations;
use Spatie\Translatable\HasTranslations;

class SafetyType extends Model
{
    use HasFactory, SoftDeletes, HasTranslations, HasAiTranslations;

    protected $table = 'fo_safety_types';

    public array $translatable = ['name'];

    public function getAiTranslatableAttributes(): array
    {
        return ['name'];
    }

    protected $fillable = ['created_by_user_id', 'name', 'ai_translation_status'];

    protected static function newFactory(): SafetyTypeFactory
    {
        return SafetyTypeFactory::new();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function structures()
    {
        return $this->hasMany(Structure::class, 'safety_type_id');
    }
}
