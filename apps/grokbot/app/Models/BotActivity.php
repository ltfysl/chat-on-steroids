<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BotActivity extends Model
{
    protected $fillable = ['public_id','workspace_id','bot_id','runtime_id','type','level','summary','payload','occurred_at'];
    protected function casts(): array { return ['payload' => 'array', 'occurred_at' => 'datetime']; }
    public function bot(): BelongsTo { return $this->belongsTo(Bot::class); }
}
