<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;
use Modules\Website\Database\Factories\ConsultationActivityFactory;

class ConsultationActivity extends Model
{
    use HasFactory;

    protected static function newFactory(): ConsultationActivityFactory
    {
        return ConsultationActivityFactory::new();
    }

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
