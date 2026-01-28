<?php

namespace App\Models\Cafca;

use App\Traits\Legacy\ReadOnlyTrait;

class Employee extends CafcaModel
{
    use ReadOnlyTrait;

    protected $table = 'employee';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $casts = [
        'fl_active' => 'boolean',
        'birthday' => 'datetime',
        'employment_date' => 'datetime',
        'termination_date' => 'datetime',
    ];

    /**
     * Get the avatar URL or a placeholder.
     */
    public function getAvatarUrlAttribute(): string
    {
        return "https://ui-avatars.com/api/?name=" . urlencode($this->name) . "&color=7F9CF5&background=EBF4FF";
    }

    /**
     * Get the job function description.
     */
    public function getJobFunctionAttribute(): string
    {
        return trim($this->attributes['functie'] ?? 'Algemeen');
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
}
