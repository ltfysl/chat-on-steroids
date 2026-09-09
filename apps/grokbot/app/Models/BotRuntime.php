<?php

namespace App\Models;

use App\Support\RuntimeDescriptor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BotRuntime extends Model
{
    protected $fillable = ['bot_id','provider','external_id','state','vcpu','memory_mb','disk_gb','image','endpoint','generation','last_heartbeat_at','lease_expires_at'];
    protected function casts(): array { return ['last_heartbeat_at' => 'datetime', 'lease_expires_at' => 'datetime']; }
    public function bot(): BelongsTo { return $this->belongsTo(Bot::class); }

    public function descriptor(): RuntimeDescriptor
    {
        return new RuntimeDescriptor($this->external_id, $this->provider, $this->state, $this->vcpu, $this->memory_mb, $this->disk_gb, $this->image, $this->endpoint, $this->generation);
    }
}
