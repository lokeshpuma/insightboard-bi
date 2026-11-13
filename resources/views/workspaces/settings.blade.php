@extends('layouts.app')

@section('title', 'Workspace Settings & Members')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">{{ $workspace->name }} Settings</h1>
        <p class="page-subtitle">Configure workspace details, manage access, and invite collaborators</p>
    </div>
</div>

<div class="grid grid-cols-12" style="margin-bottom: 2rem;">
    <!-- Workspace Settings -->
    <div class="col-span-4">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">General Settings</h2>
            </div>
            <div class="card-body">
                <form action="{{ route('workspaces.update') }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <div class="form-group">
                        <label class="form-label" for="ws-name">Workspace Name</label>
                        <input type="text" id="ws-name" name="name" class="form-control" value="{{ old('name', $workspace->name) }}" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Slug Identifier</label>
                        <input type="text" class="form-control" value="{{ $workspace->slug }}" readonly disabled style="background: #f8fafc;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Created At</label>
                        <input type="text" class="form-control" value="{{ $workspace->created_at->format('M d, Y') }}" readonly disabled style="background: #f8fafc;">
                    </div>

                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Members & Invitations -->
    <div class="col-span-8">
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header">
                <h2 class="card-title">Active Members ({{ $members->count() }})</h2>
                <button type="button" class="btn btn-primary btn-sm" onclick="openModal('invite-modal')">
                    + Invite Member
                </button>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $member)
                            <tr>
                                <td>
                                    <div style="font-weight: 600;">{{ $member->user->name }}</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $member->user->email }}</div>
                                </td>
                                <td>
                                    @if($member->role === 'owner' || $member->user_id === $workspace->owner_id)
                                        <span class="badge badge-primary" style="background: var(--primary-light); color: var(--primary);">Owner</span>
                                    @else
                                        <form action="{{ route('workspaces.members.update', $member) }}" method="POST" style="display: inline-block;">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role" class="form-select" onchange="this.form.submit()" style="padding: 0.2rem 0.5rem; font-size: 0.75rem; width: auto;">
                                                <option value="admin" {{ $member->role === 'admin' ? 'selected' : '' }}>Admin</option>
                                                <option value="analyst" {{ $member->role === 'analyst' ? 'selected' : '' }}>Analyst</option>
                                                <option value="viewer" {{ $member->role === 'viewer' ? 'selected' : '' }}>Viewer</option>
                                            </select>
                                        </form>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-success">{{ ucfirst($member->status) }}</span>
                                </td>
                                <td style="font-size: 0.8rem; color: var(--text-muted);">
                                    {{ $member->joined_at ? $member->joined_at->format('M d, Y') : '—' }}
                                </td>
                                <td>
                                    @if($member->role !== 'owner' && $member->user_id !== $workspace->owner_id)
                                        <form action="{{ route('workspaces.members.destroy', $member) }}" method="POST" onsubmit="return confirm('Remove this member from workspace?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: #fecaca;">
                                                Remove
                                            </button>
                                        </form>
                                    @else
                                        <span style="font-size: 0.75rem; color: var(--text-light);">Permanent</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if($invitations->isNotEmpty())
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Pending Invitations</h2>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Expires</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invitations as $invitation)
                                <tr>
                                    <td style="font-weight: 500;">{{ $invitation->email }}</td>
                                    <td><span class="badge badge-info">{{ ucfirst($invitation->role) }}</span></td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted);">{{ $invitation->expires_at->diffForHumans() }}</td>
                                    <td>
                                        @if($invitation->isExpired())
                                            <span class="badge badge-danger">Expired</span>
                                        @else
                                            <span class="badge badge-warning">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Modal: Invite Member -->
<div id="invite-modal" class="modal-backdrop" style="display: none;">
    <div class="modal-content">
        <form action="{{ route('workspaces.invite') }}" method="POST">
            @csrf
            <div class="modal-header">
                <h3 class="card-title">Invite New Member</h3>
                <button type="button" class="btn btn-outline btn-sm" data-dismiss="modal">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="invite-email">Email Address</label>
                    <input type="email" id="invite-email" name="email" class="form-control" placeholder="colleague@example.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="invite-role">Workspace Role</label>
                    <select id="invite-role" name="role" class="form-select" required>
                        <option value="analyst">Analyst (Uploads data, creates charts & dashboards)</option>
                        <option value="admin">Admin (Manages datasets, members, and settings)</option>
                        <option value="viewer">Viewer (Read-only access to dashboards & reports)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Send Invitation</button>
            </div>
        </form>
    </div>
</div>
@endsection

