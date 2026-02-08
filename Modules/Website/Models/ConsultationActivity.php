<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class ConsultationActivity extends Model
{
    protected $table = 'website_consultation_activities';

    protected $fillable = [
        'consultation_request_id',
        'user_id',
        'type',
        'title',
        'description',
        'data',
        'old_value',
        'new_value',
        'activity_at',
    ];

    protected $casts = [
        'data' => 'array',
        'activity_at' => 'datetime',
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
