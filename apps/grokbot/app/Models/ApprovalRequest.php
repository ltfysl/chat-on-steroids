<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ApprovalRequest extends Model
{
    protected $fillable = ['public_id','workspace_id','bot_id','action','risk','arguments','status','resolved_by','expires_at','resolved_at'];
    protected function casts(): array { return ['arguments' => 'encrypted:array', 'expires_at' => 'datetime', 'resolved_at' => 'datetime']; }
}
