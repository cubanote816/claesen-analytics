<?php

namespace Modules\Mailing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\User;

class Campaign extends Model
{
    use HasFactory;

    protected $table = 'mailing_campaigns';

    protected $fillable = [
        'created_by',
        'template_name',
        'description',
        'subject_snapshot',
        'body_snapshot',
        'total_count',
        'sent_count',
        'failed_count',
        'skipped_count',
        'status',
        'finished_at',
    ];

    protected $casts = [
        'finished_at' => 'datetime',
        'total_count'   => 'integer',
        'sent_count'    => 'integer',
        'failed_count'  => 'integer',
        'skipped_count' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }
}
