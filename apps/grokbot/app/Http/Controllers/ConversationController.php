<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class ConversationController
{
    public function store(Request $request, Bot $bot): RedirectResponse
    {
        abort_unless($request->user()->belongsToWorkspace($bot->workspace), 404);
        $conversation = Conversation::query()->create([
            'public_id' => (string) Str::uuid(), 'workspace_id' => $bot->workspace_id,
            'created_by' => $request->user()->id, 'title' => $request->string('title')->trim()->value() ?: 'New conversation', 'state' => 'active',
        ]);
        $conversation->bots()->attach($bot);
        return redirect()->route('conversations.show', $conversation);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $workspace = $request->user()->currentWorkspace();
        abort_unless($workspace && $workspace->id === $conversation->workspace_id, 404);
        $conversation->load(['bots.runtime', 'messages.chunks']);

        return Inertia::render('Conversations/Show', [
            'conversation' => $conversation->only(['public_id','title','state','created_at']),
            'bots' => $conversation->bots->map(fn (Bot $bot) => [...$bot->only(['public_id','name','model','state']), 'runtime' => $bot->runtime?->only(['state','external_id'])]),
            'messages' => $conversation->messages->map(fn ($message) => $message->only(['public_id','sender_user_id','sender_bot_id','role','status','sequence','content','metadata','started_at','completed_at','created_at'])),
        ]);
    }
}
