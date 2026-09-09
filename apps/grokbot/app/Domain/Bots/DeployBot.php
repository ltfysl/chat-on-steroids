<?php

namespace App\Domain\Bots;

use App\Contracts\TinyVmDriver;
use App\Models\Bot;
use App\Models\BotActivity;
use App\Models\BotRuntime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class DeployBot
{
    public function __construct(private TinyVmDriver $driver) {}

    public function handle(Bot $bot): BotRuntime
    {
        return DB::transaction(function () use ($bot): BotRuntime {
            $bot = Bot::query()->lockForUpdate()->findOrFail($bot->id);
            $runtime = $bot->runtime;

            if (! $runtime) {
                $descriptor = $this->driver->provision($bot);
                $runtime = BotRuntime::query()->create(['bot_id' => $bot->id, ...$descriptor->toArray()]);
            }

            if ($runtime->state !== 'running') {
                $descriptor = $this->driver->start($runtime->descriptor());
                $runtime->update($descriptor->toArray());
            }

            $bot->update(['state' => 'running', 'deployed_at' => now()]);
            BotActivity::query()->create([
                'public_id' => (string) Str::ulid(), 'workspace_id' => $bot->workspace_id, 'bot_id' => $bot->id,
                'runtime_id' => $runtime->id, 'type' => 'runtime.started', 'summary' => 'Dedicated runtime is online.',
                'payload' => ['external_id' => $runtime->external_id, 'generation' => $runtime->generation], 'occurred_at' => now(),
            ]);

            return $runtime->fresh();
        }, attempts: 3);
    }
}
