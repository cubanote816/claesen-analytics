<?php

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    protected $table = 'website_messages';

    protected $fillable = [
        'name',
        'email',
        'subject',
        'content',
        'ip_address',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];
}
