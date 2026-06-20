<?php

namespace Modules\Safety\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Safety\Database\Factories\InspectionFactory;

class Inspection extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): InspectionFactory
    {
        return InspectionFactory::new();
    }

    protected $table = 'safety_inspections';

    protected $fillable = [
        'user_id',
        'checklist_id',
        'type',
        'incident_worker_id',
        'project_id',
        'idempotency_key',
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

    public function incidentWorker(): BelongsTo
    {
        return $this->belongsTo(\Modules\Cafca\Models\Employee::class, 'incident_worker_id');
    }

    public function presentWorkers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\Modules\Cafca\Models\Employee::class, 'safety_inspection_workers', 'inspection_id', 'worker_id')->withTimestamps();
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
