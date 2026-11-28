<?php

declare(strict_types=1);

namespace Modules\Intelligence\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * CLA-714: the provider and model chosen on screen for one AI task.
 * No row = the task uses config('intelligence.ai'). Every change is audited.
 * Never stores API keys.
 */
class AiTaskSetting extends Model
{
    use LogsActivity;

    protected $table = 'intelligence_ai_task_settings';

    protected $fillable = ['task', 'provider', 'model', 'updated_by'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['task', 'provider', 'model', 'updated_by'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
