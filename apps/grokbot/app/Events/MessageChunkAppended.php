<?php

namespace App\Events;

use App\Models\MessageChunk;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MessageChunkAppended implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly MessageChunk $chunk, public readonly int $workspaceId, public readonly string $conversationPublicId) {}
    public function broadcastOn(): array { return [new PrivateChannel('workspace.'.$this->workspaceId), new PrivateChannel('conversation.'.$this->conversationPublicId)]; }
    public function broadcastAs(): string { return 'message.chunk'; }
    public function broadcastWith(): array { return ['message_id' => $this->chunk->message_id, 'sequence' => $this->chunk->sequence, 'kind' => $this->chunk->kind, 'delta' => $this->chunk->delta]; }
}
