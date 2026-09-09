<?php

namespace App\Http\Controllers;

use App\Models\BotActivity;
use App\Models\BotRuntime;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function __invoke(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace();
        abort_unless($workspace, 403);
        $botIds = $workspace->bots()->pluck('id');

        return Inertia::render('Dashboard', [
            'metrics' => [
                'bots' => $botIds->count(),
                'running' => BotRuntime::query()->whereIn('bot_id', $botIds)->where('state', 'running')->count(),
                'pendingApprovals' => $workspace->hasMany(\App\Models\ApprovalRequest::class)->where('status', 'pending')->count(),
                'events24h' => BotActivity::query()->where('workspace_id', $workspace->id)->where('occurred_at', '>=', now()->subDay())->count(),
            ],
            'bots' => $workspace->bots()->with('runtime')->latest()->limit(8)->get(),
            'activity' => BotActivity::query()->where('workspace_id', $workspace->id)->latest('occurred_at')->limit(20)->get(),
        ]);
    }
}
