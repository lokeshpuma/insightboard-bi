@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Saved Reports</h1>
        <p class="page-subtitle">Reusable analytical reports and CSV export configurations for {{ $workspace->name }}</p>
    </div>
    @if(in_array($currentRole, ['owner', 'admin', 'analyst']))
        <a href="{{ route('reports.create') }}" class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create New Report
        </a>
    @endif
</div>

@if($reports->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <h3 class="empty-state-title">No saved reports yet</h3>
            <p class="empty-state-desc">
                Save repeatable reports with predefined metrics, charts, and downloadable CSV exports.
            </p>
            @if(in_array($currentRole, ['owner', 'admin', 'analyst']))
                <a href="{{ route('reports.create') }}" class="btn btn-primary">Create Your First Report</a>
            @endif
        </div>
    </div>
@else
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Report Name</th>
                        <th>Dataset</th>
                        <th>Chart Type</th>
                        <th>Aggregation</th>
                        <th>Created By</th>
                        <th>Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reports as $report)
                        <tr>
                            <td>
                                <a href="{{ route('reports.show', $report) }}" style="font-weight: 600;">
                                    {{ $report->name }}
                                </a>
                                @if($report->description)
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">{{ Str::limit($report->description, 60) }}</div>
                                @endif
                            </td>
                            <td>{{ $report->definition['dataset_name'] ?? 'Dataset' }}</td>
                            <td><span class="badge badge-secondary">{{ ucfirst($report->definition['chart_type'] ?? 'Chart') }}</span></td>
                            <td><code>{{ strtoupper($report->definition['aggregation'] ?? 'COUNT') }}</code></td>
                            <td style="font-size: 0.85rem;">{{ $report->creator?->name ?? 'User' }}</td>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ $report->created_at->format('M d, Y') }}
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 0.4rem;">
                                    <a href="{{ route('reports.show', $report) }}" class="btn btn-outline btn-sm">View Report</a>
                                    @if(in_array($currentRole, ['owner', 'admin']) || ($currentRole === 'analyst' && $report->created_by === auth()->id()))
                                        <form action="{{ route('reports.destroy', $report) }}" method="POST" onsubmit="return confirm('Delete this report?');" style="margin: 0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: #fecaca;">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($reports->hasPages())
            <div class="card-footer">
                {{ $reports->links() }}
            </div>
        @endif
    </div>
@endif
@endsection

