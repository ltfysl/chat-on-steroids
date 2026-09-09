<?php

namespace App\Jobs;

use App\Models\Bot;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class RunBotTurn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public readonly int $botId, public readonly int $userMessageId, public readonly int $assistantMessageId) {}

    public function handle(): void
    {
        $bot = Bot::query()->with('runtime')->findOrFail($this->botId);
        $runtime = $bot->runtime;
        if (! $runtime || $runtime->state !== 'running') throw new RuntimeException('Bot runtime is not running.');

        $userMessage = Message::query()->findOrFail($this->userMessageId);
        $assistantMessage = Message::query()->findOrFail($this->assistantMessageId);

        Http::baseUrl(rtrim((string) config('tinyvm.gateway.url'), '/'))
            ->withToken((string) config('tinyvm.gateway.token'))->acceptJson()->asJson()->timeout(30)
            ->post('/v1/runtimes/'.$runtime->external_id.'/turns', [
                'bot_id' => $bot->public_id,
                'conversation_id' => $userMessage->conversation_id,
                'input' => $userMessage->content,
                'output_message_id' => $assistantMessage->public_id,
                'model' => $bot->model,
                'system_prompt' => $bot->system_prompt,
                'tools' => $bot->tools ?? [],
                'memory_enabled' => $bot->memory_enabled,
                'callbacks' => [
                    'chunks' => rtrim((string) config('app.url'), '/').'/api/runtime/messages/'.$assistantMessage->public_id.'/chunks',
                    'complete' => rtrim((string) config('app.url'), '/').'/api/runtime/messages/'.$assistantMessage->public_id.'/complete',
                ],
            ])->throw();

        $assistantMessage->update(['status' => 'streaming', 'started_at' => now()]);
    }

    public function failed(?\Throwable $exception): void
    {
        Message::query()->whereKey($this->assistantMessageId)->update([
            'status' => 'failed', 'metadata' => ['error' => $exception?->getMessage() ?? 'Runtime execution failed'], 'completed_at' => now(),
        ]);
    }
}
