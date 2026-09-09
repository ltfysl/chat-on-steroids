<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class BotController
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace();
        abort_unless($workspace, 403);

        return Inertia::render('Bots/Index', [
            'bots' => $workspace->bots()->with('runtime')->latest()->get()->map(fn (Bot $bot) => $this->present($bot)),
        ]);
    }

    public function create(): Response { return Inertia::render('Bots/Create'); }

    public function store(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace();
        abort_unless($workspace, 403);
        $data = $this->validated($request, $workspace->id);

        $bot = DB::transaction(function () use ($request, $workspace, $data): Bot {
            $bot = Bot::query()->create([
                ...$data, 'public_id' => (string) Str::uuid(), 'workspace_id' => $workspace->id,
                'owner_id' => $request->user()->id, 'state' => 'draft', 'current_version' => 1,
            ]);
            DB::table('bot_versions')->insert([
                'bot_id' => $bot->id, 'version' => 1, 'configuration' => json_encode($this->configuration($bot), JSON_THROW_ON_ERROR),
                'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $bot;
        });

        return redirect()->route('bots.show', $bot)->with('success', 'Bot created. Deploy when its policy is ready.');
    }

    public function show(Request $request, Bot $bot): Response
    {
        $this->authorizeWorkspace($request, $bot);
        $bot->load('runtime');
        return Inertia::render('Bots/Show', [
            'bot' => $this->present($bot),
            'activities' => $bot->activities()->latest('occurred_at')->limit(80)->get(),
        ]);
    }

    public function update(Request $request, Bot $bot): RedirectResponse
    {
        $this->authorizeWorkspace($request, $bot);
        $data = $this->validated($request, $bot->workspace_id, $bot);

        DB::transaction(function () use ($request, $bot, $data): void {
            $bot->lockForUpdate(); $bot->fill($data); $bot->current_version++; $bot->save();
            DB::table('bot_versions')->insert([
                'bot_id' => $bot->id, 'version' => $bot->current_version, 'configuration' => json_encode($this->configuration($bot), JSON_THROW_ON_ERROR),
                'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Configuration versioned and saved.');
    }

    public function destroy(Request $request, Bot $bot): RedirectResponse
    {
        $this->authorizeWorkspace($request, $bot);
        abort_if($bot->runtime?->state === 'running', 409, 'Stop the bot before deleting it.');
        $bot->delete();
        return redirect()->route('bots.index')->with('success', 'Bot deleted.');
    }

    private function validated(Request $request, int $workspaceId, ?Bot $bot = null): array
    {
        return $request->validate([
            'name' => ['required','string','max:100'],
            'slug' => ['required','alpha_dash','max:100', Rule::unique('bots')->where('workspace_id', $workspaceId)->ignore($bot?->id)],
            'description' => ['nullable','string','max:2000'], 'model' => ['required','string','max:120'],
            'system_prompt' => ['nullable','string','max:50000'], 'tools' => ['nullable','array'], 'tools.*' => ['string','max:100'],
            'memory_enabled' => ['required','boolean'], 'runtime_profile' => ['nullable','array'], 'network_policy' => ['nullable','array'],
        ]);
    }

    private function authorizeWorkspace(Request $request, Bot $bot): void
    {
        abort_unless($request->user()->belongsToWorkspace($bot->workspace), 404);
    }

    private function configuration(Bot $bot): array
    {
        return $bot->only(['name','slug','description','model','system_prompt','tools','network_policy','runtime_profile','memory_enabled']);
    }

    private function present(Bot $bot): array
    {
        return [
            ...$bot->only(['public_id','name','slug','description','state','model','tools','network_policy','runtime_profile','memory_enabled','current_version','deployed_at']),
            'runtime' => $bot->runtime?->only(['provider','external_id','state','vcpu','memory_mb','disk_gb','image','generation','last_heartbeat_at']),
        ];
    }
}
