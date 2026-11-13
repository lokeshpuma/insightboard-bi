@extends('layouts.app')

@section('title', $report->name)

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">{{ $report->name }}</h1>
        <p class="page-subtitle">
            Source dataset: <strong>{{ $report->definition['dataset_name'] ?? 'Dataset' }}</strong> &bull; 
            Aggregation: <code>{{ strtoupper($report->definition['aggregation'] ?? 'COUNT') }}</code> &bull; 
            Generated {{ now()->format('M d, Y') }}
        </p>
    </div>

    <div style="display: flex; gap: 0.75rem;">
        <form action="{{ route('exports.store') }}" method="POST" style="margin: 0;">
            @csrf
            <input type="hidden" name="resource_type" value="report">
            <input type="hidden" name="resource_id" value="{{ $report->id }}">
            <button type="submit" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                Export to CSV
            </button>
        </form>

        <button type="button" class="btn btn-outline" onclick="window.print()">
            Print Report
        </button>
    </div>
</div>

@if($report->description)
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-body" style="padding: 1rem; font-size: 0.9rem; color: var(--text-muted);">
            {{ $report->description }}
        </div>
    </div>
@endif

@if($chartData && !empty($chartData['labels']))
    <div class="grid grid-cols-12" style="margin-bottom: 2rem;">
        <!-- Chart Canvas -->
        <div class="col-span-8">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Visualization</h2>
                </div>
                <div class="card-body" style="height: 350px;">
                    <canvas id="report-chart"></canvas>
                </div>
            </div>
        </div>

        <!-- Summary Table -->
        <div class="col-span-4">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Data Summary</h2>
                </div>
                <div class="table-responsive" style="max-height: 350px;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th style="text-align: right;">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($chartData['labels'] as $idx => $label)
                                <tr>
                                    <td><strong>{{ $label }}</strong></td>
                                    <td style="text-align: right; font-weight: 600;">
                                        {{ number_format($chartData['series'][0]['data'][$idx] ?? 0, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">📊</div>
            <h3 class="empty-state-title">No aggregated data available</h3>
            <p class="empty-state-desc">The source dataset may be empty or processing. Check dataset status.</p>
        </div>
    </div>
@endif
@endsection

@push('scripts')
@if($chartData && !empty($chartData['labels']))
<script>
document.addEventListener('DOMContentLoaded', () => {
    const chartConfig = @json($chartData);
    InsightCharts.render('report-chart', chartConfig);
});
</script>
@endif
@endpush

