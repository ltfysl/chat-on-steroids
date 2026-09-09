<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Conversation extends Model
{
    protected $fillable = ['public_id','workspace_id','created_by','title','state'];
    public function bots(): BelongsToMany { return $this->belongsToMany(Bot::class)->withTimestamps(); }
    public function messages(): HasMany { return $this->hasMany(Message::class)->orderBy('sequence'); }
    public function getRouteKeyName(): string { return 'public_id'; }
}
