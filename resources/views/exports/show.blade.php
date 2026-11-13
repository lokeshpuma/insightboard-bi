@extends('layouts.app')

@section('title', 'CSV Export Status')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">CSV Export Status</h1>
        <p class="page-subtitle">Export ID: <code>{{ $exportJob->id }}</code></p>
    </div>
</div>

<div class="grid grid-cols-12">
    <div class="col-span-8">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Export Job Details</h2>
                <span id="export-status-badge" class="badge {{ $exportJob->status === 'completed' ? 'badge-success' : ($exportJob->status === 'failed' ? 'badge-danger' : 'badge-warning') }}">
                    {{ ucfirst($exportJob->status) }}
                </span>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 1.5rem;">
                    <div style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.25rem;">Resource Type</div>
                    <div style="font-weight: 600;">{{ ucfirst($exportJob->resource_type) }}</div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <div style="font-size: 0.875rem; color: var(--text-muted); margin-bottom: 0.25rem;">Initiated By</div>
                    <div>{{ $exportJob->requester?->name }} ({{ $exportJob->created_at->format('M d, Y H:i:s') }})</div>
                </div>

                <div id="download-section" style="{{ $exportJob->status === 'completed' ? '' : 'display: none;' }}">
                    <div class="alert alert-success">
                        Your CSV export file is ready. Formula injection protection has been applied.
                    </div>
                    <a href="{{ route('exports.download', $exportJob) }}" class="btn btn-primary btn-lg">
                        Download CSV File →
                    </a>
                </div>

                <div id="processing-section" style="{{ $exportJob->status === 'pending' || $exportJob->status === 'processing' ? '' : 'display: none;' }}">
                    <div class="alert alert-info">
                        Generating CSV export in the background. This page will update automatically.
                    </div>
                </div>

                <div id="error-section" style="{{ $exportJob->status === 'failed' ? '' : 'display: none;' }}">
                    <div class="alert alert-danger">
                        {{ $exportJob->error_message ?? 'Export failed to process.' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if($exportJob->status !== 'completed' && $exportJob->status !== 'failed')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const statusUrl = "{{ route('exports.status', $exportJob) }}";
    const downloadSection = document.getElementById('download-section');
    const processingSection = document.getElementById('processing-section');
    const errorSection = document.getElementById('error-section');
    const badge = document.getElementById('export-status-badge');

    const interval = setInterval(() => {
        fetch(statusUrl, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(res => res.json())
        .then(json => {
            if (json.success && json.data) {
                const status = json.data.status;
                if (status === 'completed') {
                    clearInterval(interval);
                    badge.className = 'badge badge-success';
                    badge.textContent = 'Completed';
                    processingSection.style.display = 'none';
                    downloadSection.style.display = 'block';
                } else if (status === 'failed') {
                    clearInterval(interval);
                    badge.className = 'badge badge-danger';
                    badge.textContent = 'Failed';
                    processingSection.style.display = 'none';
                    errorSection.style.display = 'block';
                }
            }
        })
        .catch(err => console.error(err));
    }, 2000);
});
</script>
@endif
@endpush

