<?php

namespace App\Policies;

use App\Models\ExportJob;
use App\Models\User;
use App\Models\Workspace;

class ExportJobPolicy
{
    public function create(User $user, Workspace $workspace): bool
    {
        return $user->hasWorkspaceRole($workspace, ['owner', 'admin', 'analyst']);
    }

    public function view(User $user, ExportJob $exportJob): bool
    {
        if (!$user->hasWorkspaceRole($exportJob->workspace, ['owner', 'admin', 'analyst'])) {
            return false;
        }

        $role = $user->roleInWorkspace($exportJob->workspace);
        if (in_array($role, ['owner', 'admin'], true)) {
            return true;
        }

        return $exportJob->requested_by === $user->id;
    }

    public function download(User $user, ExportJob $exportJob): bool
    {
        return $this->view($user, $exportJob);
    }
}

