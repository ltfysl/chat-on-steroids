<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Message extends Model
{
    protected $fillable = ['public_id','conversation_id','sender_user_id','sender_bot_id','role','status','sequence','content','metadata','token_count','started_at','completed_at'];
    protected function casts(): array { return ['metadata' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime']; }
    public function chunks(): HasMany { return $this->hasMany(MessageChunk::class)->orderBy('sequence'); }
}
