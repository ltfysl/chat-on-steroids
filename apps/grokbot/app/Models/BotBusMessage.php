<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class BotBusMessage extends Model
{
    protected $fillable = ['public_id','workspace_id','sender_bot_id','recipient_bot_id','topic','correlation_id','causation_id','payload','status','delivered_at','acknowledged_at'];
    protected function casts(): array { return ['payload' => 'array', 'delivered_at' => 'datetime', 'acknowledged_at' => 'datetime']; }
}
