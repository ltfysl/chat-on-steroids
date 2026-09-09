<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TeamController
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace();
        abort_unless($workspace, 403);
        return Inertia::render('Team/Index', [
            'members' => $workspace->users()->get()->map(fn (User $user) => [...$user->only(['id','name','email']), 'role' => $user->pivot->role]),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace();
        abort_unless($workspace && $workspace->users()->whereKey($user->id)->exists(), 404);
        $actorRole = $request->user()->workspaces()->whereKey($workspace->id)->value('role');
        abort_unless($actorRole === 'owner', 403);
        abort_if($workspace->owner_id === $user->id, 422, 'Workspace owner role cannot be changed.');
        $data = $request->validate(['role' => ['required','in:admin,member,viewer']]);
        $workspace->users()->updateExistingPivot($user->id, ['role' => $data['role']]);
        return back()->with('success', 'Role updated.');
    }
}
