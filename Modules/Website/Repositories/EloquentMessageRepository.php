<?php

namespace Modules\Website\Repositories;

use Modules\Website\Models\Message;
use Modules\Website\Contracts\MessageRepositoryInterface;
use Modules\Website\DTOs\MessageData;

class EloquentMessageRepository implements MessageRepositoryInterface
{
    public function store(MessageData $data): Message
    {
        return Message::create([
            'name' => $data->name,
            'email' => $data->email,
            'subject' => $data->subject,
            'content' => $data->content,
            'ip_address' => $data->ip_address,
            'is_read' => false,
        ]);
    }
}
