<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;
use App\Models\Workspace;

class ReportPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, ['owner', 'admin', 'analyst', 'viewer']);
    }

    public function view(User $user, Report $report): bool
    {
        return $user->hasWorkspaceRole($report->workspace, ['owner', 'admin', 'analyst', 'viewer']);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, ['owner', 'admin', 'analyst']);
    }

    public function update(User $user, Report $report): bool
    {
        $role = $user->roleInWorkspace($report->workspace);
        if (in_array($role, ['owner', 'admin'], true)) {
            return true;
        }

        return $role === 'analyst' && $report->created_by === $user->id;
    }

    public function delete(User $user, Report $report): bool
    {
        $role = $user->roleInWorkspace($report->workspace);
        if (in_array($role, ['owner', 'admin'], true)) {
            return true;
        }

        return $role === 'analyst' && $report->created_by === $user->id;
    }
}

