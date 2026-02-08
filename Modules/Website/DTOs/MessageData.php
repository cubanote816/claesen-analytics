<?php

namespace Modules\Website\DTOs;

class MessageData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $subject,
        public string $content,
        public ?string $ip_address = null,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'content' => $this->content,
            'ip_address' => $this->ip_address,
        ];
    }
}
