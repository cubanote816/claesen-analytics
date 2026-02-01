<?php

namespace App\Models;

use App\Models\Cafca\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectInsight extends Model
{
    protected $connection = 'mysql';
    protected $table = 'project_insights';

    public function getRouteKeyName()
    {
        return 'project_id';
    }

    protected $fillable = [
        'project_id',
        'insight_type',
        'efficiency_score',
        'critical_leak',
        'ai_summary',
        'golden_rule',
        'full_dna',
        'last_data_hash',
        'last_audited_at',
    ];

    protected $casts = [
        'efficiency_score' => 'decimal:2',
        'last_audited_at' => 'datetime',
        'full_dna' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id', 'id');
    }
}
