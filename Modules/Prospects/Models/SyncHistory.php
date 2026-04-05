<?php
namespace Modules\Prospects\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;

class SyncHistory extends Model
{
    protected $table = 'prospects_sync_histories';

    protected $fillable = [
        'command',
        'type',
        'status',
        'records_count',
        'logs',
        'started_at',
        'finished_at',
        'user_id',
    ];

    protected $casts = [
        'logs' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'records_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
