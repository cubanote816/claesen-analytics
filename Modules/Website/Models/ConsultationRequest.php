<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;

class ConsultationRequest extends Model
{
    use HasFactory;

    protected $table = 'website_consultation_requests';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'type',
        'project_type',
        'message',
        'preferred_contact',
        'status',
        'internal_notes',
        'contacted_at',
        'assigned_to',
        'priority',
        'source',
        'estimated_value',
        'actual_value',
        'currency',
        'follow_up_date',
        'follow_up_notes',
        'tags',
        'custom_fields',
        'last_activity_at',
        'activity_count',
    ];

    protected $casts = [
        'contacted_at' => 'datetime',
        'follow_up_date' => 'date',
        'last_activity_at' => 'datetime',
        'tags' => 'array',
        'custom_fields' => 'array',
        'estimated_value' => 'decimal:2',
        'actual_value' => 'decimal:2',
    ];

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities()
    {
        return $this->hasMany(ConsultationActivity::class);
    }

    public function reminders()
    {
        return $this->hasMany(ConsultationReminder::class);
    }

    public function notifications()
    {
        return $this->hasMany(ConsultationNotification::class);
    }
}
