<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;
use Modules\Website\Database\Factories\ConsultationNotificationFactory;

class ConsultationNotification extends Model
{
    use HasFactory;

    protected static function newFactory(): ConsultationNotificationFactory
    {
        return ConsultationNotificationFactory::new();
    }

    protected $table = 'website_consultation_notifications';

    protected $fillable = [
        'consultation_request_id',
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'priority',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
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
