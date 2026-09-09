<?php

namespace App\Infrastructure\TinyVm;

use App\Contracts\TinyVmDriver;
use App\Models\Bot;
use App\Support\RuntimeDescriptor;

final class FakeTinyVmDriver implements TinyVmDriver
{
    public function provision(Bot $bot): RuntimeDescriptor
    {
        $profile = array_replace(config('tinyvm.default'), $bot->runtime_profile ?? []);

        return new RuntimeDescriptor(
            externalId: 'fake-'.$bot->public_id,
            provider: 'fake',
            state: 'stopped',
            vcpu: (int) $profile['vcpu'],
            memoryMb: (int) $profile['memory_mb'],
            diskGb: (int) $profile['disk_gb'],
            image: (string) $profile['image'],
            endpoint: 'http://runtime-'.$bot->public_id.'.internal',
        );
    }

    public function start(RuntimeDescriptor $runtime): RuntimeDescriptor { return $this->withState($runtime, 'running'); }
    public function stop(RuntimeDescriptor $runtime): RuntimeDescriptor { return $this->withState($runtime, 'stopped'); }
    public function destroy(RuntimeDescriptor $runtime): void {}

    public function health(RuntimeDescriptor $runtime): array
    {
        return ['state' => $runtime->state, 'cpu_percent' => 0.0, 'memory_bytes' => 0, 'disk_bytes' => 0, 'observed_at' => now()->toIso8601String()];
    }

    private function withState(RuntimeDescriptor $runtime, string $state): RuntimeDescriptor
    {
        return new RuntimeDescriptor($runtime->externalId, $runtime->provider, $state, $runtime->vcpu, $runtime->memoryMb, $runtime->diskGb, $runtime->image, $runtime->endpoint, $runtime->generation);
    }
}
