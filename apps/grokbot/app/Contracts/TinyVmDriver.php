<?php

namespace App\Contracts;

use App\Models\Bot;
use App\Support\RuntimeDescriptor;

interface TinyVmDriver
{
    public function provision(Bot $bot): RuntimeDescriptor;

    public function start(RuntimeDescriptor $runtime): RuntimeDescriptor;

    public function stop(RuntimeDescriptor $runtime): RuntimeDescriptor;

    public function destroy(RuntimeDescriptor $runtime): void;

    /** @return array{state:string,cpu_percent:float,memory_bytes:int,disk_bytes:int,observed_at:string} */
    public function health(RuntimeDescriptor $runtime): array;
}
