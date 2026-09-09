<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Bot extends Model
{
    use HasFactory;

    protected $fillable = ['public_id','workspace_id','owner_id','name','slug','description','state','model','system_prompt','tools','network_policy','runtime_profile','memory_enabled','current_version','deployed_at'];

    protected function casts(): array
    {
        return ['tools' => 'array', 'network_policy' => 'array', 'runtime_profile' => 'array', 'memory_enabled' => 'boolean', 'deployed_at' => 'datetime'];
    }

    public function workspace(): BelongsTo { return $this->belongsTo(Workspace::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function runtime(): HasOne { return $this->hasOne(BotRuntime::class); }
    public function activities(): HasMany { return $this->hasMany(BotActivity::class); }

    public function getRouteKeyName(): string { return 'public_id'; }
}
