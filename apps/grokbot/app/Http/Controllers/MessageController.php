<?php

namespace App\Http\Controllers;

use App\Jobs\RunBotTurn;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MessageController
{
    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace();
        abort_unless($workspace && $workspace->id === $conversation->workspace_id, 404);
        $data = $request->validate(['content' => ['required','string','max:100000'], 'bot_id' => ['nullable','uuid']]);

        $botQuery = $conversation->bots()->with('runtime');
        $bot = isset($data['bot_id']) ? $botQuery->where('bots.public_id', $data['bot_id'])->first() : $botQuery->first();
        abort_unless($bot, 422, 'Conversation has no matching bot.');
        abort_unless($bot->runtime?->state === 'running', 409, 'Deploy the bot before sending a message.');

        [$userMessage, $assistantMessage] = DB::transaction(function () use ($request, $conversation, $bot, $data): array {
            Conversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $next = ((int) Message::query()->where('conversation_id', $conversation->id)->max('sequence')) + 1;
            $userMessage = Message::query()->create([
                'public_id' => (string) Str::ulid(), 'conversation_id' => $conversation->id, 'sender_user_id' => $request->user()->id,
                'role' => 'user', 'status' => 'complete', 'sequence' => $next, 'content' => $data['content'], 'completed_at' => now(),
            ]);
            $assistantMessage = Message::query()->create([
                'public_id' => (string) Str::ulid(), 'conversation_id' => $conversation->id, 'sender_bot_id' => $bot->id,
                'role' => 'assistant', 'status' => 'pending', 'sequence' => $next + 1, 'content' => '',
            ]);
            return [$userMessage, $assistantMessage];
        }, attempts: 3);

        RunBotTurn::dispatch($bot->id, $userMessage->id, $assistantMessage->id)->afterCommit();

        return back(303);
    }
}
