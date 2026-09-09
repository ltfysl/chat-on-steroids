<?php

use App\Domain\Bus\BotCommunicationBus;
use Illuminate\Auth\Access\AuthorizationException;

it('persists direct bot communication inside one workspace', function () {
    $sender = isolatedBotFixture('Sender');
    $recipient = \App\Models\Bot::query()->create([
        'public_id' => (string) \Illuminate\Support\Str::uuid(), 'workspace_id' => $sender->workspace_id, 'owner_id' => $sender->owner_id,
        'name' => 'Recipient', 'slug' => 'recipient', 'state' => 'draft', 'model' => 'test-model', 'tools' => [],
        'network_policy' => ['mode' => 'deny-by-default', 'allow' => []], 'runtime_profile' => [], 'memory_enabled' => true,
    ]);

    $message = app(BotCommunicationBus::class)->direct($sender, $recipient, 'tasks.delegate', ['task' => 'review']);

    expect($message->workspace_id)->toBe($sender->workspace_id)
        ->and($message->recipient_bot_id)->toBe($recipient->id)
        ->and($message->payload)->toBe(['task' => 'review'])
        ->and($message->status)->toBe('queued');
});

it('fails closed when bots belong to different workspaces', function () {
    $sender = isolatedBotFixture('Workspace A');
    $recipient = isolatedBotFixture('Workspace B');

    expect(fn () => app(BotCommunicationBus::class)->direct($sender, $recipient, 'tasks.delegate', ['secret' => true]))
        ->toThrow(AuthorizationException::class);

    expect(\App\Models\BotBusMessage::query()->count())->toBe(0);
});
