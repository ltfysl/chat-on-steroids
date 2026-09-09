<?php

use App\Models\Bot;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

function isolatedBotFixture(string $name): Bot
{
    $user = User::query()->create([
        'name' => $name.' Owner',
        'email' => Str::lower(Str::random(8)).'@example.test',
        'password' => 'correct horse battery staple',
    ]);
    $workspace = Workspace::query()->create([
        'public_id' => (string) Str::uuid(),
        'name' => $name.' Workspace',
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
        'owner_id' => $user->id,
    ]);
    $workspace->users()->attach($user, ['role' => 'owner']);

    return Bot::query()->create([
        'public_id' => (string) Str::uuid(),
        'workspace_id' => $workspace->id,
        'owner_id' => $user->id,
        'name' => $name,
        'slug' => Str::slug($name),
        'state' => 'draft',
        'model' => 'test-model',
        'tools' => [],
        'network_policy' => ['mode' => 'deny-by-default', 'allow' => []],
        'runtime_profile' => ['vcpu' => 1, 'memory_mb' => 512, 'disk_gb' => 2, 'image' => 'test:latest'],
        'memory_enabled' => true,
    ]);
}
