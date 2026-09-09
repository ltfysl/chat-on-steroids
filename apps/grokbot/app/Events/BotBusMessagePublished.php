<?php

namespace App\Events;

use App\Models\BotBusMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class BotBusMessagePublished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly BotBusMessage $message) {}

    public function broadcastOn(): array { return [new PrivateChannel('workspace.'.$this->message->workspace_id)]; }
    public function broadcastAs(): string { return 'bot.bus.message'; }
    public function broadcastWith(): array { return ['message' => $this->message->only(['public_id','sender_bot_id','recipient_bot_id','topic','correlation_id','payload','status','created_at'])]; }
}
