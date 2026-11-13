@extends('layouts.app')

@section('title', 'Overview')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">{{ $workspace->name }} Overview</h1>
        <p class="page-subtitle">Real-time metrics, recent dataset imports, and dashboard activity</p>
    </div>
    <div style="display: flex; gap: 0.75rem;">
        @if(in_array($currentRole, ['owner', 'admin', 'analyst']))
            <a href="{{ route('datasets.create') }}" class="btn btn-outline">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                Upload CSV
            </a>
            <a href="{{ route('dashboards.create') }}" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Dashboard
            </a>
        @endif
    </div>
</div>

<!-- KPI Summary Cards -->
<div class="grid grid-cols-4" style="margin-bottom: 2rem;">
    <div class="kpi-card">
        <span class="kpi-title">Datasets</span>
        <div class="kpi-value">{{ number_format($datasetCount) }}</div>
        <span class="kpi-subtext">Active CSV files</span>
    </div>

    <div class="kpi-card">
        <span class="kpi-title">Total Records</span>
        <div class="kpi-value">{{ number_format($totalRows) }}</div>
        <span class="kpi-subtext">Rows parsed in MySQL</span>
    </div>

    <div class="kpi-card">
        <span class="kpi-title">Dashboards</span>
        <div class="kpi-value">{{ number_format($dashboardCount) }}</div>
        <span class="kpi-subtext">Visual layouts</span>
    </div>

    <div class="kpi-card">
        <span class="kpi-title">Reports</span>
        <div class="kpi-value">{{ number_format($reportCount) }}</div>
        <span class="kpi-subtext">Saved export definitions</span>
    </div>
</div>

@if($datasetCount === 0)
    <!-- Empty State for new workspace -->
    <div class="card" style="margin-bottom: 2rem;">
        <div class="empty-state">
            <div class="empty-state-icon">📊</div>
            <h3 class="empty-state-title">No datasets uploaded yet</h3>
            <p class="empty-state-desc">
                Welcome to InsightBoard! Upload your first CSV file to inspect data quality, detect column schemas, and build interactive dashboards.
            </p>
            @if(in_array($currentRole, ['owner', 'admin', 'analyst']))
                <a href="{{ route('datasets.create') }}" class="btn btn-primary btn-lg">
                    Upload Your First CSV Dataset →
                </a>
            @endif
        </div>
    </div>
@else
    <div class="grid grid-cols-12" style="margin-bottom: 2rem;">
        <!-- Recent Datasets -->
        <div class="col-span-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Datasets</h2>
                    <a href="{{ route('datasets.index') }}" class="btn btn-outline btn-sm">View All Datasets</a>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Records</th>
                                <th>Columns</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($recentDatasets as $dataset)
                                <tr>
                                    <td>
                                        <a href="{{ route('datasets.show', $dataset) }}" style="font-weight: 600;">
                                            {{ $dataset->name }}
                                        </a>
                                        <div style="font-size: 0.75rem; color: var(--text-light);">{{ $dataset->original_filename }}</div>
                                    </td>
                                    <td>
                                        @if($dataset->status === 'ready')
                                            <span class="badge badge-success">Ready</span>
                                        @elseif($dataset->status === 'processing')
                                            <span class="badge badge-warning">Processing</span>
                                        @elseif($dataset->status === 'failed')
                                            <span class="badge badge-danger">Failed</span>
                                        @else
                                            <span class="badge badge-info">{{ ucfirst($dataset->status) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ number_format($dataset->row_count) }}</td>
                                    <td>{{ $dataset->column_count }}</td>
                                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                                        {{ $dataset->created_at->diffForHumans() }}
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <a href="{{ route('datasets.show', $dataset) }}" class="btn btn-outline btn-sm">Profile</a>
                                            @if($dataset->isReady())
                                                <a href="{{ route('datasets.explorer', $dataset) }}" class="btn btn-secondary btn-sm">Explore</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Recent Dashboards -->
        <div class="col-span-4">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Dashboards</h2>
                    <a href="{{ route('dashboards.index') }}" class="btn btn-outline btn-sm">View All</a>
                </div>
                <div class="card-body">
                    @if($recentDashboards->isEmpty())
                        <div style="text-align: center; padding: 2rem 1rem; color: var(--text-muted); font-size: 0.875rem;">
                            No dashboards created yet.<br>
                            <a href="{{ route('dashboards.create') }}" style="font-weight: 600; margin-top: 0.5rem; display: inline-block;">+ Create Dashboard</a>
                        </div>
                    @else
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            @foreach($recentDashboards as $dashboard)
                                <div style="padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--radius-md); background: #fafafa;">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                        <a href="{{ route('dashboards.show', $dashboard) }}" style="font-weight: 600; font-size: 0.95rem;">
                                            {{ $dashboard->name }}
                                        </a>
                                        <span class="badge badge-secondary">{{ $dashboard->visibility }}</span>
                                    </div>
                                    @if($dashboard->description)
                                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0.25rem 0;">
                                            {{ Str::limit($dashboard->description, 60) }}
                                        </p>
                                    @endif
                                    <div style="font-size: 0.75rem; color: var(--text-light); margin-top: 0.35rem;">
                                        Updated {{ $dashboard->updated_at->diffForHumans() }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif

<!-- Recent Workspace Audit Activity (for Owners/Admins) -->
@if(isset($recentAuditLogs) && $recentAuditLogs->isNotEmpty())
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Recent Workspace Activity</h2>
            <a href="{{ route('audit-logs.index') }}" class="btn btn-outline btn-sm">Audit Trail</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Resource</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentAuditLogs as $log)
                        <tr>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ $log->created_at->format('M d, H:i') }}
                            </td>
                            <td>
                                <span style="font-weight: 500;">{{ $log->actor?->name ?? 'System' }}</span>
                            </td>
                            <td>
                                <span class="badge badge-secondary">{{ str_replace('_', ' ', $log->action) }}</span>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);">
                                {{ $log->resource_type ? ucfirst($log->resource_type) : '—' }}
                            </td>
                            <td style="font-size: 0.8rem; color: var(--text-muted); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $log->metadata ? json_encode($log->metadata) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection

