<?php

namespace App\Support;

final readonly class RuntimeDescriptor
{
    public function __construct(
        public string $externalId,
        public string $provider,
        public string $state,
        public int $vcpu,
        public int $memoryMb,
        public int $diskGb,
        public string $image,
        public ?string $endpoint = null,
        public int $generation = 1,
    ) {}

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'provider' => $this->provider,
            'state' => $this->state,
            'vcpu' => $this->vcpu,
            'memory_mb' => $this->memoryMb,
            'disk_gb' => $this->diskGb,
            'image' => $this->image,
            'endpoint' => $this->endpoint,
            'generation' => $this->generation,
        ];
    }
}
