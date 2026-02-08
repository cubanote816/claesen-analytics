<?php

namespace Modules\Website\Contracts;

use Modules\Website\Models\Message;
use Modules\Website\DTOs\MessageData;

interface MessageRepositoryInterface
{
    public function store(MessageData $data): Message;
}
