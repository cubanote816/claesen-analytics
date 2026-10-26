<?php

namespace Modules\Knx\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One execution of an acceptance test. Never updated, never deleted: it is the
 * evidence that a test was actually run, and what it looked like at the time.
 */
class KnxTestExecution extends Model
{
    use HasFactory;

    protected $table = 'knx_test_executions';

    protected $fillable = ['acceptance_test_id', 'status', 'observed', 'note', 'executor_employee_id', 'executed_at'];

    protected function casts(): array
    {
        return ['executed_at' => 'datetime'];
    }

    public function acceptanceTest(): BelongsTo
    {
        return $this->belongsTo(KnxAcceptanceTest::class, 'acceptance_test_id');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(KnxEmployee::class, 'executor_employee_id');
    }
}
