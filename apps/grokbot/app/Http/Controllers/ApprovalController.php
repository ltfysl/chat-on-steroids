<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class ApprovalController
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace();
        abort_unless($workspace, 403);
        return Inertia::render('Approvals/Index', [
            'approvals' => ApprovalRequest::query()->where('workspace_id', $workspace->id)->latest()->paginate(50),
        ]);
    }

    public function update(Request $request, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace();
        abort_unless($workspace && $workspace->id === $approvalRequest->workspace_id, 404);
        $role = $request->user()->workspaces()->whereKey($workspace->id)->value('role');
        abort_unless(in_array($role, ['owner','admin'], true), 403);
        $data = $request->validate(['decision' => ['required','in:approved,rejected']]);

        DB::transaction(function () use ($approvalRequest, $request, $data): void {
            $approval = ApprovalRequest::query()->lockForUpdate()->findOrFail($approvalRequest->id);
            abort_unless($approval->status === 'pending', 409, 'Approval is already resolved.');
            abort_if($approval->expires_at?->isPast(), 409, 'Approval request has expired.');
            $approval->update(['status' => $data['decision'], 'resolved_by' => $request->user()->id, 'resolved_at' => now()]);
        });

        return back()->with('success', 'Approval decision recorded.');
    }
}
