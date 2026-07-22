<?php

namespace Modules\FieldOps\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Models\User;
use Modules\Intelligence\Traits\HasAiTranslations;
use Spatie\Translatable\HasTranslations;

class Luminaire extends Model
{
    use HasAiTranslations, HasFactory, HasTranslations, SoftDeletes;

    protected static function newFactory()
    {
        return \Modules\FieldOps\Database\Factories\LuminaireFactory::new();
    }

    protected $table = 'fo_luminaires';

    public array $translatable = ['info'];

    public function getAiTranslatableAttributes(): array
    {
        return ['info'];
    }

    protected $fillable = [
        'created_by_user_id',
        'luminaire_type_id',
        'luminaire_subgroup_id',
        'luminaire_frame_id',
        'luminaire_position_id',
        'active_position_id',
        'frame_position',
        'serial_number',
        'frame_x',
        'frame_y',
        'scale_x',
        'scale_y',
        'position_version',
        'position_source',
        'position_verified_by_user_id',
        'position_verified_at',
        'info',
        'cafca_material_id',
        'installed_at',
        'removed_at',
        'removal_reason',
        'replaced_by_luminaire_id',
        'ai_translation_status',
    ];

    protected $casts = [
        'frame_x' => 'float',
        'frame_y' => 'float',
        'scale_x' => 'float',
        'scale_y' => 'float',
        'position_version' => 'integer',
        'frame_position' => 'integer',
        'position_verified_at' => 'datetime',
        'installed_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if ($model->frame_position === null) {
                $max = Schema::hasTable('fo_luminaire_positions')
                    ? LuminairePosition::where('luminaire_frame_id', $model->luminaire_frame_id)->max('frame_position')
                    : static::where('luminaire_frame_id', $model->luminaire_frame_id)->max('frame_position');
                $model->frame_position = $max ? $max + 1 : 1;
            }

            if (! Schema::hasTable('fo_luminaire_positions')) {
                return;
            }

            $position = $model->luminaire_position_id
                ? LuminairePosition::findOrFail($model->luminaire_position_id)
                : LuminairePosition::firstOrCreate(
                    [
                        'luminaire_frame_id' => $model->luminaire_frame_id,
                        'frame_position' => $model->frame_position,
                    ],
                    [
                        'frame_x' => $model->frame_x ?? 0,
                        'frame_y' => $model->frame_y ?? 0,
                        'scale_x' => $model->scale_x,
                        'scale_y' => $model->scale_y,
                        'position_version' => max((int) ($model->position_version ?? 1), 1),
                        'position_source' => $model->position_source,
                        'position_verified_by_user_id' => $model->position_verified_by_user_id,
                        'position_verified_at' => $model->position_verified_at,
                    ],
                );

            $model->forceFill([
                'luminaire_position_id' => $position->id,
                'active_position_id' => $model->removed_at === null ? $position->id : null,
                'luminaire_frame_id' => $position->luminaire_frame_id,
                'frame_position' => $position->frame_position,
                'frame_x' => $position->frame_x,
                'frame_y' => $position->frame_y,
                'scale_x' => $position->scale_x,
                'scale_y' => $position->scale_y,
                'position_version' => $position->position_version,
                'position_source' => $position->position_source,
                'position_verified_by_user_id' => $position->position_verified_by_user_id,
                'position_verified_at' => $position->position_verified_at,
                'installed_at' => $model->installed_at ?? now(),
            ]);
        });

        static::saved(function (self $model): void {
            if (! $model->luminaire_position_id || ! Schema::hasTable('fo_luminaire_positions')) {
                return;
            }

            $spatialAttributes = [
                'luminaire_frame_id',
                'frame_position',
                'frame_x',
                'frame_y',
                'scale_x',
                'scale_y',
                'position_version',
                'position_source',
                'position_verified_by_user_id',
                'position_verified_at',
            ];

            if (! collect($spatialAttributes)->contains(fn (string $attribute): bool => $model->wasChanged($attribute))) {
                return;
            }

            LuminairePosition::whereKey($model->luminaire_position_id)->update([
                'luminaire_frame_id' => $model->luminaire_frame_id,
                'frame_position' => $model->frame_position,
                'frame_x' => $model->frame_x,
                'frame_y' => $model->frame_y,
                'scale_x' => $model->scale_x,
                'scale_y' => $model->scale_y,
                'position_version' => max((int) ($model->position_version ?? 1), 1),
                'position_source' => $model->position_source,
                'position_verified_by_user_id' => $model->position_verified_by_user_id,
                'position_verified_at' => $model->position_verified_at,
            ]);
        });
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function luminaireFrame()
    {
        return $this->belongsTo(LuminaireFrame::class, 'luminaire_frame_id');
    }

    public function position()
    {
        return $this->belongsTo(LuminairePosition::class, 'luminaire_position_id');
    }

    public function replacement()
    {
        return $this->belongsTo(self::class, 'replaced_by_luminaire_id');
    }

    public function previousInstallation()
    {
        return $this->hasOne(self::class, 'replaced_by_luminaire_id');
    }

    public function scopeCurrent($query)
    {
        return $query->whereNull('removed_at')->whereNotNull('active_position_id');
    }

    public function luminaireType()
    {
        return $this->belongsTo(LuminaireType::class);
    }

    public function subgroup()
    {
        return $this->belongsTo(LuminaireSubgroup::class, 'luminaire_subgroup_id');
    }

    public function maintenanceRecords()
    {
        return $this->morphMany(FoMaintenanceRecord::class, 'maintainable');
    }

    public function maintenanceWorkOrders()
    {
        return $this->morphMany(FoMaintenanceWorkOrder::class, 'maintainable');
    }

    public function maintenancePlans()
    {
        return $this->morphMany(FoMaintenancePlan::class, 'maintainable');
    }
}
