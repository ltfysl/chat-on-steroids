<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'password' => 'hashed']; }

    public function workspaces(): BelongsToMany { return $this->belongsToMany(Workspace::class)->withPivot('role')->withTimestamps(); }
    public function currentWorkspace(): ?Workspace { return $this->workspaces()->orderBy('workspaces.id')->first(); }
    public function belongsToWorkspace(Workspace $workspace): bool { return $this->workspaces()->whereKey($workspace->getKey())->exists(); }
}
