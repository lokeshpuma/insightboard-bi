<?php

namespace App\Policies;

use App\Models\Dashboard;
use App\Models\User;
use App\Models\Workspace;

class DashboardPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, ['owner', 'admin', 'analyst', 'viewer']);
    }

    public function view(User $user, Dashboard $dashboard): bool
    {
        if (!$user->hasWorkspaceRole($dashboard->workspace, ['owner', 'admin', 'analyst', 'viewer'])) {
            return false;
        }

        if ($dashboard->visibility === 'private' && $dashboard->created_by !== $user->id) {
            return $user->hasWorkspaceRole($dashboard->workspace, ['owner', 'admin']);
        }

        return true;
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, ['owner', 'admin', 'analyst']);
    }

    public function update(User $user, Dashboard $dashboard): bool
    {
        $role = $user->roleInWorkspace($dashboard->workspace);
        if (in_array($role, ['owner', 'admin'], true)) {
            return true;
        }

        return $role === 'analyst' && $dashboard->created_by === $user->id;
    }

    public function delete(User $user, Dashboard $dashboard): bool
    {
        $role = $user->roleInWorkspace($dashboard->workspace);
        if (in_array($role, ['owner', 'admin'], true)) {
            return true;
        }

        return $role === 'analyst' && $dashboard->created_by === $user->id;
    }
}

