@extends('layouts.app')

@section('title', 'Audit History')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Workspace Audit Trail</h1>
        <p class="page-subtitle">Historical log of security, dataset imports, dashboard changes, and export actions</p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Resource Type</th>
                    <th>Resource ID</th>
                    <th>IP Address</th>
                    <th>Metadata</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                            {{ $log->created_at->format('Y-m-d H:i:s') }}
                        </td>
                        <td>
                            <div style="font-weight: 600;">{{ $log->actor?->name ?? 'System' }}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $log->actor?->email ?? 'internal' }}</div>
                        </td>
                        <td>
                            <span class="badge badge-secondary">{{ str_replace('_', ' ', $log->action) }}</span>
                        </td>
                        <td style="font-size: 0.85rem;">
                            {{ $log->resource_type ? ucfirst($log->resource_type) : '—' }}
                        </td>
                        <td style="font-size: 0.75rem; font-family: monospace; color: var(--text-muted);">
                            {{ $log->resource_id ? Str::limit($log->resource_id, 12) : '—' }}
                        </td>
                        <td style="font-size: 0.8rem; color: var(--text-light);">
                            {{ $log->ip_address ?? '—' }}
                        </td>
                        <td style="font-size: 0.8rem; font-family: monospace; color: var(--text-main); max-width: 300px;">
                            {{ $log->metadata ? json_encode($log->metadata) : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No audit records logged yet for this workspace.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.875rem; color: var(--text-muted);">
                Showing page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}
            </div>
            <div>
                {{ $logs->links() }}
            </div>
        </div>
    @endif
</div>
@endsection

