<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Employee extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile();
    }
    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'employees';

    /**
     * The primary key for the model.
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        'function',
        'mobile',
        'email',
        'street',
        'zip',
        'city',
        'country',
        'fl_active',
        'birth_date',
        'employment_date',
        'termination_date',
        'legacy_ts_modif',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     * @var array
     */
    protected $casts = [
        'fl_active' => 'boolean',
        'birth_date' => 'datetime',
        'employment_date' => 'datetime',
        'termination_date' => 'datetime',
        'legacy_ts_modif' => 'datetime',
    ];

    /**
     * Get the avatar URL or a placeholder.
     * Logic kept from legacy for continuity.
     */
    public function getAvatarUrlAttribute(): string
    {
        return $this->getFirstMediaUrl('avatar')
            ?: "https://ui-avatars.com/api/?name=" . urlencode($this->name) . "&color=7F9CF5&background=EBF4FF";
    }

    /**
     * Get the job function description.
     */
    public function getJobFunctionAttribute(): string
    {
        return trim($this->attributes['function'] ?? __('employees/resource.placeholders.no_function'));
    }

    /**
     * Get the formatted address.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->attributes['street'] ?? null,
            trim(($this->attributes['zip'] ?? '') . ' ' . ($this->attributes['city'] ?? '')),
            $this->attributes['country'] ?? null,
        ]);

        return implode(', ', $parts);
    }

    public function insight(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EmployeeInsight::class, 'employee_id', 'id');
    }
}
