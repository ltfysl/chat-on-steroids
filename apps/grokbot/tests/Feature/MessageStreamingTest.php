<?php

use App\Domain\Messages\AppendMessageChunk;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Str;

it('persists every chunk and materializes the message body', function () {
    $bot = isolatedBotFixture('Streamer');
    $conversation = Conversation::query()->create([
        'public_id' => (string) Str::uuid(), 'workspace_id' => $bot->workspace_id,
        'created_by' => $bot->owner_id, 'title' => 'Streaming test', 'state' => 'active',
    ]);
    $message = Message::query()->create([
        'public_id' => (string) Str::ulid(), 'conversation_id' => $conversation->id,
        'sender_bot_id' => $bot->id, 'role' => 'assistant', 'status' => 'pending', 'sequence' => 1, 'content' => '', 'started_at' => now(),
    ]);
    $append = app(AppendMessageChunk::class);

    $append->handle($message, 1, 'Hello');
    $append->handle($message, 2, ' world');

    expect($message->fresh()->content)->toBe('Hello world')
        ->and($message->chunks()->count())->toBe(2)
        ->and($message->chunks()->pluck('sequence')->all())->toBe([1, 2]);
});

it('rejects out of order chunks without mutating content', function () {
    $bot = isolatedBotFixture('Ordered');
    $conversation = Conversation::query()->create(['public_id' => (string) Str::uuid(), 'workspace_id' => $bot->workspace_id, 'created_by' => $bot->owner_id, 'title' => 'Ordering', 'state' => 'active']);
    $message = Message::query()->create(['public_id' => (string) Str::ulid(), 'conversation_id' => $conversation->id, 'sender_bot_id' => $bot->id, 'role' => 'assistant', 'status' => 'pending', 'sequence' => 1, 'content' => '']);

    expect(fn () => app(AppendMessageChunk::class)->handle($message, 2, 'wrong'))->toThrow(DomainException::class);
    expect($message->fresh()->content)->toBe('')->and($message->chunks()->count())->toBe(0);
});
