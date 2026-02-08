<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ConsultationNotification extends Model
{
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
