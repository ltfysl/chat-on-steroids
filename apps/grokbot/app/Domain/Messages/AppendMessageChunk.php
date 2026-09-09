<?php

namespace App\Domain\Messages;

use App\Models\Message;
use App\Models\MessageChunk;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AppendMessageChunk
{
    public function handle(Message $message, int $sequence, string $delta, string $kind = 'text'): MessageChunk
    {
        return DB::transaction(function () use ($message, $sequence, $delta, $kind): MessageChunk {
            $message = Message::query()->lockForUpdate()->findOrFail($message->id);
            $expected = ((int) $message->chunks()->max('sequence')) + 1;

            if ($sequence !== $expected) {
                throw new DomainException("Out-of-order message chunk: expected {$expected}, got {$sequence}.");
            }
            if ($message->status === 'complete') {
                throw new DomainException('Completed messages are immutable.');
            }

            $chunk = MessageChunk::query()->create(['message_id' => $message->id, 'sequence' => $sequence, 'kind' => $kind, 'delta' => $delta]);
            $message->update(['content' => $message->content.$delta, 'status' => 'streaming']);

            return $chunk;
        }, attempts: 3);
    }
}
