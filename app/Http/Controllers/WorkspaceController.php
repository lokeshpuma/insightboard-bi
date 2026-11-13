<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    protected function getActiveWorkspace(Request $request): Workspace
    {
        return $request->attributes->get('currentWorkspace');
    }

    public function switch(Workspace $workspace)
    {
        $user = Auth::user();

        if (!$user->roleInWorkspace($workspace)) {
            abort(403, 'Unauthorized access to this workspace.');
        }

        session(['current_workspace_id' => $workspace->id]);

        return redirect()->route('overview')->with('info', "Switched to workspace: {$workspace->name}");
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $user = Auth::user();

        $workspace = DB::transaction(function () use ($user, $validated) {
            $slug = Str::slug($validated['name']) . '-' . Str::lower(Str::random(5));
            $workspace = Workspace::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'owner_id' => $user->id,
            ]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            AuditLog::record($workspace, 'workspace_created', 'workspace', (string) $workspace->id, ['name' => $workspace->name], $user);

            return $workspace;
        });

        session(['current_workspace_id' => $workspace->id]);

        return redirect()->route('overview')->with('success', "Workspace '{$workspace->name}' created.");
    }

    public function settings(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('view', $workspace);

        $members = $workspace->members()->with('user')->get();
        $invitations = $workspace->invitations()->whereNull('accepted_at')->get();

        return view('workspaces.settings', compact('workspace', 'members', 'invitations'));
    }

    public function update(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('update', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $oldName = $workspace->name;
        $workspace->update(['name' => $validated['name']]);

        AuditLog::record($workspace, 'workspace_updated', 'workspace', (string) $workspace->id, [
            'old_name' => $oldName,
            'new_name' => $workspace->name,
        ]);

        return back()->with('success', 'Workspace settings updated.');
    }

    public function invite(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('manageMembers', $workspace);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:admin,analyst,viewer'],
        ]);

        // Check if already a member
        $existingUser = User::where('email', $validated['email'])->first();
        if ($existingUser && $existingUser->roleInWorkspace($workspace)) {
            return back()->withErrors(['email' => 'User is already an active member of this workspace.']);
        }

        // If user already exists in system, add them directly
        if ($existingUser) {
            WorkspaceMember::updateOrCreate(
                ['workspace_id' => $workspace->id, 'user_id' => $existingUser->id],
                ['role' => $validated['role'], 'status' => 'active', 'joined_at' => now()]
            );

            AuditLog::record($workspace, 'member_added', 'user', (string) $existingUser->id, [
                'email' => $existingUser->email,
                'role' => $validated['role'],
            ]);

            return back()->with('success', "{$existingUser->name} ({$existingUser->email}) added as {$validated['role']}.");
        }

        // Otherwise create an invitation
        $token = Str::random(32);
        WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
            'invited_by' => Auth::id(),
        ]);

        AuditLog::record($workspace, 'member_invited', 'invitation', null, [
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        return back()->with('success', "Invitation sent to {$validated['email']}.");
    }

    public function updateMember(Request $request, WorkspaceMember $member)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('manageMembers', $workspace);

        if ($member->workspace_id !== $workspace->id) {
            abort(403);
        }

        // Cannot change owner role
        if ($member->role === 'owner' || $member->user_id === $workspace->owner_id) {
            return back()->withErrors(['error' => 'Cannot modify the workspace owner.']);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:admin,analyst,viewer'],
        ]);

        $oldRole = $member->role;
        $member->update(['role' => $validated['role']]);

        AuditLog::record($workspace, 'member_role_updated', 'user', (string) $member->user_id, [
            'old_role' => $oldRole,
            'new_role' => $validated['role'],
        ]);

        return back()->with('success', 'Member role updated.');
    }

    public function removeMember(Request $request, WorkspaceMember $member)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('manageMembers', $workspace);

        if ($member->workspace_id !== $workspace->id) {
            abort(403);
        }

        if ($member->role === 'owner' || $member->user_id === $workspace->owner_id) {
            return back()->withErrors(['error' => 'Cannot remove the workspace owner.']);
        }

        $user = $member->user;
        $member->delete();

        AuditLog::record($workspace, 'member_removed', 'user', (string) $user->id, [
            'email' => $user->email,
        ]);

        return back()->with('success', 'Member removed from workspace.');
    }
}

