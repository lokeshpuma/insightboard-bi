<?php

namespace App\Policies;

use App\Models\Dataset;
use App\Models\User;
use App\Models\Workspace;

class DatasetPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, ['owner', 'admin', 'analyst', 'viewer']);
    }

    public function view(User $user, Dataset $dataset): bool
    {
        return $user->hasWorkspaceRole($dataset->workspace, ['owner', 'admin', 'analyst', 'viewer']);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, ['owner', 'admin', 'analyst']);
    }

    public function update(User $user, Dataset $dataset): bool
    {
        $role = $user->roleInWorkspace($dataset->workspace);
        if (in_array($role, ['owner', 'admin'], true)) {
            return true;
        }

        return $role === 'analyst' && $dataset->created_by === $user->id;
    }

    public function delete(User $user, Dataset $dataset): bool
    {
        $role = $user->roleInWorkspace($dataset->workspace);
        if (in_array($role, ['owner', 'admin'], true)) {
            return true;
        }

        return $role === 'analyst' && $dataset->created_by === $user->id;
    }

    public function reprofile(User $user, Dataset $dataset): bool
    {
        return $this->update($user, $dataset);
    }
}

