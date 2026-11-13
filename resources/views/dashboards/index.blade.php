@extends('layouts.app')

@section('title', 'Dashboards')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Dashboards</h1>
        <p class="page-subtitle">Interactive analytical dashboards assembled for {{ $workspace->name }}</p>
    </div>
    @if(in_array($currentRole, ['owner', 'admin', 'analyst']))
        <button type="button" class="btn btn-primary" onclick="openModal('create-dashboard-modal')">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create Dashboard
        </button>
    @endif
</div>

@if($dashboards->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">📈</div>
            <h3 class="empty-state-title">No dashboards created yet</h3>
            <p class="empty-state-desc">
                Assemble KPI metric cards and charts from your imported datasets into responsive dashboards.
            </p>
            @if(in_array($currentRole, ['owner', 'admin', 'analyst']))
                <button type="button" class="btn btn-primary" onclick="openModal('create-dashboard-modal')">
                    Create Your First Dashboard
                </button>
            @endif
        </div>
    </div>
@else
    <div class="grid grid-cols-3">
        @foreach($dashboards as $dashboard)
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
                <div class="card-body">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                        <h2 style="font-size: 1.1rem; font-weight: 700;">
                            <a href="{{ route('dashboards.show', $dashboard) }}" style="color: var(--text-main);">
                                {{ $dashboard->name }}
                            </a>
                        </h2>
                        <span class="badge {{ $dashboard->visibility === 'private' ? 'badge-warning' : 'badge-secondary' }}">
                            {{ ucfirst($dashboard->visibility) }}
                        </span>
                    </div>

                    @if($dashboard->description)
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">
                            {{ Str::limit($dashboard->description, 100) }}
                        </p>
                    @endif

                    <div style="display: flex; gap: 1rem; font-size: 0.8rem; color: var(--text-light); margin-top: 1rem;">
                        <span>{{ $dashboard->widgets->count() }} widgets</span>
                        <span>&bull;</span>
                        <span>By {{ $dashboard->creator?->name ?? 'User' }}</span>
                    </div>
                </div>

                <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.75rem; color: var(--text-light);">
                        Updated {{ $dashboard->updated_at->diffForHumans() }}
                    </span>
                    <a href="{{ route('dashboards.show', $dashboard) }}" class="btn btn-outline btn-sm">
                        Open Dashboard →
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    @if($dashboards->hasPages())
        <div style="margin-top: 1.5rem;">
            {{ $dashboards->links() }}
        </div>
    @endif
@endif

<!-- Modal: Create Dashboard -->
<div id="create-dashboard-modal" class="modal-backdrop" style="display: none;">
    <div class="modal-content">
        <form action="{{ route('dashboards.store') }}" method="POST">
            @csrf
            <div class="modal-header">
                <h3 class="card-title">New Dashboard</h3>
                <button type="button" class="btn btn-outline btn-sm" data-dismiss="modal">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="dash-name">Dashboard Title</label>
                    <input type="text" id="dash-name" name="name" class="form-control" placeholder="e.g. Sales Executive Summary, Support Performance" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="dash-desc">Description (Optional)</label>
                    <textarea id="dash-desc" name="description" class="form-control" rows="3" placeholder="Explain the key objectives and audience for this dashboard"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="dash-visibility">Visibility</label>
                    <select id="dash-visibility" name="visibility" class="form-select" required>
                        <option value="workspace">Workspace (All members can view)</option>
                        <option value="private">Private (Only you and workspace admins)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Dashboard</button>
            </div>
        </form>
    </div>
</div>
@endsection

