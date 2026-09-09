<?php

use App\Domain\Bots\DeployBot;
use App\Models\Bot;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

function isolatedBotFixture(string $name): Bot
{
    $user = User::query()->create(['name' => $name.' Owner', 'email' => Str::lower(Str::random(8)).'@example.test', 'password' => 'correct horse battery staple']);
    $workspace = Workspace::query()->create(['public_id' => (string) Str::uuid(), 'name' => $name.' Workspace', 'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)), 'owner_id' => $user->id]);
    $workspace->users()->attach($user, ['role' => 'owner']);

    return Bot::query()->create([
        'public_id' => (string) Str::uuid(), 'workspace_id' => $workspace->id, 'owner_id' => $user->id,
        'name' => $name, 'slug' => Str::slug($name), 'state' => 'draft', 'model' => 'test-model',
        'tools' => [], 'network_policy' => ['mode' => 'deny-by-default', 'allow' => []],
        'runtime_profile' => ['vcpu' => 1, 'memory_mb' => 512, 'disk_gb' => 2, 'image' => 'test:latest'],
        'memory_enabled' => true,
    ]);
}

it('provisions a different runtime identity for every bot', function () {
    $alpha = isolatedBotFixture('Alpha');
    $beta = isolatedBotFixture('Beta');
    $deploy = app(DeployBot::class);

    $alphaRuntime = $deploy->handle($alpha);
    $betaRuntime = $deploy->handle($beta);

    expect($alphaRuntime->bot_id)->toBe($alpha->id)
        ->and($betaRuntime->bot_id)->toBe($beta->id)
        ->and($alphaRuntime->external_id)->not->toBe($betaRuntime->external_id);
});

it('makes deploy idempotent for the same bot', function () {
    $bot = isolatedBotFixture('Idempotent');
    $deploy = app(DeployBot::class);

    $first = $deploy->handle($bot);
    $second = $deploy->handle($bot->fresh());

    expect($second->id)->toBe($first->id)
        ->and($bot->runtime()->count())->toBe(1)
        ->and($second->external_id)->toBe($first->external_id);
});
