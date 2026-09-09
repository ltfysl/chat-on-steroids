<?php

namespace App\Http\Controllers;

use App\Contracts\TinyVmDriver;
use App\Domain\Bots\DeployBot;
use App\Models\Bot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final readonly class BotDeploymentController
{
    public function __construct(private DeployBot $deploy, private TinyVmDriver $driver) {}

    public function store(Request $request, Bot $bot): RedirectResponse
    {
        abort_unless($request->user()->belongsToWorkspace($bot->workspace), 404);
        $runtime = $this->deploy->handle($bot);
        return back()->with('success', "Runtime {$runtime->external_id} is running.");
    }

    public function destroy(Request $request, Bot $bot): RedirectResponse
    {
        abort_unless($request->user()->belongsToWorkspace($bot->workspace), 404);
        $runtime = $bot->runtime;
        if (! $runtime) return back();
        $descriptor = $this->driver->stop($runtime->descriptor());
        $runtime->update($descriptor->toArray());
        $bot->update(['state' => 'stopped']);
        return back()->with('success', 'Dedicated runtime stopped.');
    }
}
