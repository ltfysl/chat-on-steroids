<?php

namespace App\Http\Controllers;

use App\Domain\Messages\AppendMessageChunk;
use App\Events\MessageChunkAppended;
use App\Models\Bot;
use App\Models\BotActivity;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class RuntimeCallbackController
{
    public function __construct(private AppendMessageChunk $append) {}

    public function chunk(Request $request, Message $message): JsonResponse
    {
        $data = $request->validate([
            'sequence' => ['required','integer','min:1'],
            'delta' => ['required','string','max:50000'],
            'kind' => ['sometimes','string','in:text,reasoning,tool,meta'],
        ]);
        $conversation = Conversation::query()->findOrFail($message->conversation_id);
        $chunk = $this->append->handle($message, (int) $data['sequence'], $data['delta'], $data['kind'] ?? 'text');
        MessageChunkAppended::dispatch($chunk, $conversation->workspace_id, $conversation->public_id);

        return response()->json(['ok' => true, 'sequence' => $chunk->sequence], 202);
    }

    public function complete(Request $request, Message $message): JsonResponse
    {
        $data = $request->validate(['token_count' => ['nullable','integer','min:0'], 'metadata' => ['nullable','array']]);
        abort_if($message->status === 'complete', 409, 'Message already completed.');
        $message->update(['status' => 'complete', 'token_count' => $data['token_count'] ?? null, 'metadata' => $data['metadata'] ?? $message->metadata, 'completed_at' => now()]);
        return response()->json(['ok' => true]);
    }

    public function heartbeat(Request $request, Bot $bot): JsonResponse
    {
        $data = $request->validate([
            'external_id' => ['required','string','max:255'], 'state' => ['required','string','max:32'],
            'cpu_percent' => ['required','numeric','min:0'], 'memory_bytes' => ['required','integer','min:0'], 'disk_bytes' => ['required','integer','min:0'],
        ]);
        $runtime = $bot->runtime;
        abort_unless($runtime && hash_equals($runtime->external_id, $data['external_id']), 409, 'Runtime identity mismatch.');
        $runtime->update(['state' => $data['state'], 'last_heartbeat_at' => now()]);
        BotActivity::query()->create([
            'public_id' => (string) Str::ulid(), 'workspace_id' => $bot->workspace_id, 'bot_id' => $bot->id, 'runtime_id' => $runtime->id,
            'type' => 'runtime.heartbeat', 'level' => 'debug', 'summary' => 'Runtime heartbeat received.',
            'payload' => ['cpu_percent' => (float) $data['cpu_percent'], 'memory_bytes' => $data['memory_bytes'], 'disk_bytes' => $data['disk_bytes']], 'occurred_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }
}
