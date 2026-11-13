@extends('layouts.app')

@section('title', 'Datasets')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Dataset Library</h1>
        <p class="page-subtitle">CSV files uploaded and processed for {{ $workspace->name }}</p>
    </div>
    @if(in_array($currentRole, ['owner', 'admin', 'analyst']))
        <a href="{{ route('datasets.create') }}" class="btn btn-primary">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
            Upload CSV Dataset
        </a>
    @endif
</div>

<!-- Search & Filters -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1rem;">
        <form action="{{ route('datasets.index') }}" method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <input type="text" name="search" class="form-control" placeholder="Search by dataset or filename..." value="{{ request('search') }}">
            </div>

            <div style="width: 160px;">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="ready" {{ request('status') === 'ready' ? 'selected' : '' }}>Ready</option>
                    <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>Processing</option>
                    <option value="uploaded" {{ request('status') === 'uploaded' ? 'selected' : '' }}>Uploaded</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
                </select>
            </div>

            <div style="width: 160px;">
                <select name="sort" class="form-select">
                    <option value="created_at" {{ request('sort') === 'created_at' ? 'selected' : '' }}>Date Added</option>
                    <option value="name" {{ request('sort') === 'name' ? 'selected' : '' }}>Dataset Name</option>
                    <option value="row_count" {{ request('sort') === 'row_count' ? 'selected' : '' }}>Row Count</option>
                </select>
            </div>

            <button type="submit" class="btn btn-secondary">Filter</button>
            @if(request()->hasAny(['search', 'status', 'sort']))
                <a href="{{ route('datasets.index') }}" class="btn btn-outline">Reset</a>
            @endif
        </form>
    </div>
</div>

@if($datasets->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">📁</div>
            <h3 class="empty-state-title">No datasets found</h3>
            <p class="empty-state-desc">
                @if(request()->hasAny(['search', 'status']))
                    No datasets match your search criteria. Try adjusting filters or search terms.
                @else
                    Upload your first CSV dataset to begin inspecting data quality and building analytics dashboards.
                @endif
            </p>
            @if(in_array($currentRole, ['owner', 'admin', 'analyst']))
                <a href="{{ route('datasets.create') }}" class="btn btn-primary">Upload CSV File</a>
            @endif
        </div>
    </div>
@else
    <div class="card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Dataset</th>
                        <th>Status</th>
                        <th>Rows</th>
                        <th>Columns</th>
                        <th>File Size</th>
                        <th>Uploaded By</th>
                        <th>Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($datasets as $dataset)
                        <tr>
                            <td>
                                <a href="{{ route('datasets.show', $dataset) }}" style="font-weight: 600; font-size: 0.95rem;">
                                    {{ $dataset->name }}
                                </a>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    {{ $dataset->original_filename }} (delimiter: <code>{{ $dataset->delimiter ?: ',' }}</code>)
                                </div>
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
                            <td style="font-weight: 600;">{{ number_format($dataset->row_count) }}</td>
                            <td>{{ $dataset->column_count }}</td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);">
                                {{ round($dataset->file_size_bytes / 1024, 1) }} KB
                            </td>
                            <td style="font-size: 0.85rem;">{{ $dataset->creator?->name ?? 'System' }}</td>
                            <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                                {{ $dataset->created_at->format('M d, Y') }}
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <div style="display: inline-flex; gap: 0.4rem;">
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

        @if($datasets->hasPages())
            <div class="card-footer">
                {{ $datasets->links() }}
            </div>
        @endif
    </div>
@endif
@endsection

