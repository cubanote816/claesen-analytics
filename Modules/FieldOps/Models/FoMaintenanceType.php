<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Core\Models\User;
use Modules\FieldOps\Database\Factories\FoMaintenanceTypeFactory;
use Modules\Intelligence\Traits\HasAiTranslations;
use Spatie\Translatable\HasTranslations;

class FoMaintenanceType extends Model
{
    use HasFactory, SoftDeletes, HasTranslations, HasAiTranslations;

    public const CODE_PREVENTIVE = 'preventive';
    public const CODE_CORRECTIVE = 'corrective';
    public const CODE_EMERGENCY = 'emergency';

    protected $table = 'fo_maintenance_types';

    public array $translatable = ['name'];

    public function getAiTranslatableAttributes(): array
    {
        return ['name'];
    }

    protected $fillable = [
        'created_by_user_id',
        'name',
        'code',
        'ai_translation_status',
    ];

    protected static function newFactory(): FoMaintenanceTypeFactory
    {
        return FoMaintenanceTypeFactory::new();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(FoMaintenanceRecord::class, 'fo_maintenance_type_id');
    }
}
