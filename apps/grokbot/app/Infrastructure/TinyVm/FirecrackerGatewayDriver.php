<?php

namespace App\Infrastructure\TinyVm;

use App\Contracts\TinyVmDriver;
use App\Models\Bot;
use App\Support\RuntimeDescriptor;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class FirecrackerGatewayDriver implements TinyVmDriver
{
    public function provision(Bot $bot): RuntimeDescriptor
    {
        $profile = array_replace(config('tinyvm.default'), $bot->runtime_profile ?? []);
        $response = $this->client()->post('/v1/runtimes', [
            'idempotency_key' => 'bot:'.$bot->public_id,
            'bot_id' => $bot->public_id,
            'vcpu' => (int) $profile['vcpu'],
            'memory_mb' => (int) $profile['memory_mb'],
            'disk_gb' => (int) $profile['disk_gb'],
            'image' => (string) $profile['image'],
            'network_policy' => $bot->network_policy ?? ['mode' => 'deny-by-default', 'allow' => []],
        ])->throw()->json();

        return $this->descriptor($response);
    }

    public function start(RuntimeDescriptor $runtime): RuntimeDescriptor { return $this->transition($runtime, 'start'); }
    public function stop(RuntimeDescriptor $runtime): RuntimeDescriptor { return $this->transition($runtime, 'stop'); }

    public function destroy(RuntimeDescriptor $runtime): void
    {
        $this->client()->delete('/v1/runtimes/'.$runtime->externalId)->throw();
    }

    public function health(RuntimeDescriptor $runtime): array
    {
        return $this->client()->get('/v1/runtimes/'.$runtime->externalId.'/health')->throw()->json();
    }

    private function transition(RuntimeDescriptor $runtime, string $action): RuntimeDescriptor
    {
        return $this->descriptor($this->client()->post('/v1/runtimes/'.$runtime->externalId.'/'.$action)->throw()->json());
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('tinyvm.gateway.url'), '/'))
            ->withToken((string) config('tinyvm.gateway.token'))
            ->acceptJson()->asJson()->timeout((int) config('tinyvm.gateway.timeout', 15))->retry(2, 150, throw: false);
    }

    /** @param array<string,mixed> $payload */
    private function descriptor(array $payload): RuntimeDescriptor
    {
        return new RuntimeDescriptor((string) $payload['external_id'], 'firecracker', (string) $payload['state'], (int) $payload['vcpu'], (int) $payload['memory_mb'], (int) $payload['disk_gb'], (string) $payload['image'], $payload['endpoint'] ?? null, (int) ($payload['generation'] ?? 1));
    }
}
