<?php

namespace App\Domain\Bus;

use App\Models\Bot;
use App\Models\BotBusMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

final class BotCommunicationBus
{
    /** @param array<string,mixed> $payload */
    public function direct(Bot $sender, Bot $recipient, string $topic, array $payload, ?string $correlationId = null): BotBusMessage
    {
        if ($sender->workspace_id !== $recipient->workspace_id) {
            throw new AuthorizationException('Bots may not communicate across workspace boundaries.');
        }

        return BotBusMessage::query()->create([
            'public_id' => (string) Str::ulid(), 'workspace_id' => $sender->workspace_id,
            'sender_bot_id' => $sender->id, 'recipient_bot_id' => $recipient->id,
            'topic' => $topic, 'correlation_id' => $correlationId ?? (string) Str::uuid(),
            'payload' => $payload, 'status' => 'queued',
        ]);
    }

    /** @param array<string,mixed> $payload */
    public function publish(Bot $sender, string $topic, array $payload, ?string $correlationId = null): BotBusMessage
    {
        return BotBusMessage::query()->create([
            'public_id' => (string) Str::ulid(), 'workspace_id' => $sender->workspace_id,
            'sender_bot_id' => $sender->id, 'recipient_bot_id' => null, 'topic' => $topic,
            'correlation_id' => $correlationId ?? (string) Str::uuid(), 'payload' => $payload, 'status' => 'queued',
        ]);
    }
}
