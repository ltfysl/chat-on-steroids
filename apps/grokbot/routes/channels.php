<?php

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('workspace.{workspace}', fn (User $user, Workspace $workspace): bool => $user->belongsToWorkspace($workspace));
Broadcast::channel('bot.{bot}', fn (User $user, Bot $bot): bool => $user->belongsToWorkspace($bot->workspace));
Broadcast::channel('conversation.{conversation}', function (User $user, Conversation $conversation): bool {
    return $user->workspaces()->whereKey($conversation->workspace_id)->exists();
});
