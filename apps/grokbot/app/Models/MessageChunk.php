<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class MessageChunk extends Model
{
    protected $fillable = ['message_id', 'sequence', 'kind', 'delta'];
}
