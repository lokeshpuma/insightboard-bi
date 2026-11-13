<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, ['owner', 'admin', 'analyst', 'viewer']);
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, ['owner', 'admin']);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, ['owner', 'admin']);
    }

    public function viewAuditLogs(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, ['owner', 'admin']);
    }
}

