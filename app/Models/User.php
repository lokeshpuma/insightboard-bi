<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class, 'user_id');
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot(['role', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function roleInWorkspace(Workspace $workspace): ?string
    {
        if ($workspace->owner_id === $this->id) {
            return 'owner';
        }

        $membership = $this->workspaceMemberships()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'active')
            ->first();

        return $membership?->role;
    }

    public function hasWorkspaceRole(Workspace $workspace, array $roles): bool
    {
        $role = $this->roleInWorkspace($workspace);
        if (!$role) {
            return false;
        }

        if ($role === 'owner') {
            return true;
        }

        return in_array($role, $roles, true);
    }
}
