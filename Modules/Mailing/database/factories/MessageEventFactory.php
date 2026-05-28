<?php

namespace Modules\Mailing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Mailing\Enums\MessageEventType;
use Modules\Mailing\Models\CampaignMessage;
use Modules\Mailing\Models\MessageEvent;

class MessageEventFactory extends Factory
{
    protected $model = MessageEvent::class;

    public function definition(): array
    {
        return [
            'message_id'  => CampaignMessage::factory(),
            'event_type'  => MessageEventType::SENT,
            'occurred_at' => now(),
            'metadata'    => null,
        ];
    }

    public function opened(string $ip = '1.2.3.4'): static
    {
        return $this->state([
            'event_type' => MessageEventType::OPENED,
            'metadata'   => ['ip' => $ip, 'user_agent' => 'TestAgent/1.0'],
        ]);
    }

    public function clicked(string $url = 'https://example.com'): static
    {
        return $this->state([
            'event_type' => MessageEventType::CLICKED,
            'metadata'   => ['link_url' => $url, 'ip' => '1.2.3.4', 'user_agent' => 'TestAgent/1.0'],
        ]);
    }
}
