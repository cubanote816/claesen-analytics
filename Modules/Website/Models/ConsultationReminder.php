<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;
use Modules\Website\Database\Factories\ConsultationReminderFactory;

class ConsultationReminder extends Model
{
    use HasFactory;

    protected static function newFactory(): ConsultationReminderFactory
    {
        return ConsultationReminderFactory::new();
    }

    protected $table = 'website_consultation_reminders';

    protected $fillable = [
        'consultation_request_id',
        'user_id',
        'title',
        'description',
        'remind_at',
        'status',
        'type',
        'notification_methods',
        'completed_at',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'completed_at' => 'datetime',
        'notification_methods' => 'array',
    ];

    public function request()
    {
        return $this->belongsTo(ConsultationRequest::class, 'consultation_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
