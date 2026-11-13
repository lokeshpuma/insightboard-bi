@extends('layouts.app')

@section('title', $dataset->name . ' — Data Profile')

@section('content')
<div class="page-header">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
            <h1 class="page-title">{{ $dataset->name }}</h1>
            @if($dataset->status === 'ready')
                <span class="badge badge-success">Ready</span>
            @elseif($dataset->status === 'processing')
                <span class="badge badge-warning">Processing</span>
            @elseif($dataset->status === 'failed')
                <span class="badge badge-danger">Failed</span>
            @else
                <span class="badge badge-info">{{ ucfirst($dataset->status) }}</span>
            @endif
        </div>
        <p class="page-subtitle">
            Source file: <code>{{ $dataset->original_filename }}</code> &bull; 
            Uploaded by {{ $dataset->creator?->name ?? 'System' }} &bull; 
            {{ $dataset->created_at->format('M d, Y H:i') }}
        </p>
    </div>

    <div style="display: flex; gap: 0.75rem;">
        @if($dataset->isReady())
            <a href="{{ route('datasets.explorer', $dataset) }}" class="btn btn-primary">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
                Explore Data Rows
            </a>
        @endif

        @if(in_array($currentRole, ['owner', 'admin']) || ($currentRole === 'analyst' && $dataset->created_by === auth()->id()))
            <form action="{{ route('datasets.reprofile', $dataset) }}" method="POST" style="margin: 0;">
                @csrf
                <button type="submit" class="btn btn-outline" title="Re-run statistical profiling with Python">
                    Re-Profile
                </button>
            </form>

            <form action="{{ route('datasets.destroy', $dataset) }}" method="POST" onsubmit="return confirm('Permanently delete this dataset, imported rows, and associated files?');" style="margin: 0;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline" style="color: var(--danger); border-color: #fecaca;">
                    Delete
                </button>
            </form>
        @endif
    </div>
</div>

<!-- Dataset Metric Cards -->
<div class="grid grid-cols-4" style="margin-bottom: 2rem;">
    <div class="kpi-card">
        <span class="kpi-title">Total Rows</span>
        <div class="kpi-value">{{ number_format($dataset->row_count) }}</div>
        <span class="kpi-subtext">Parsed records</span>
    </div>

    <div class="kpi-card">
        <span class="kpi-title">Total Columns</span>
        <div class="kpi-value">{{ $dataset->column_count }}</div>
        <span class="kpi-subtext">Detected fields</span>
    </div>

    <div class="kpi-card">
        <span class="kpi-title">Duplicate Rows</span>
        @php
            $dupCount = $dataset->analysis_summary['quality']['duplicate_row_count'] ?? 0;
            $dupPct = $dataset->analysis_summary['quality']['duplicate_row_percentage'] ?? 0.0;
        @endphp
        <div class="kpi-value" style="{{ $dupCount > 0 ? 'color: var(--warning);' : '' }}">
            {{ number_format($dupCount) }}
        </div>
        <span class="kpi-subtext">{{ $dupPct }}% of dataset</span>
    </div>

    <div class="kpi-card">
        <span class="kpi-title">File Size</span>
        <div class="kpi-value">{{ round($dataset->file_size_bytes / 1024, 1) }} <span style="font-size: 1rem; font-weight: 500;">KB</span></div>
        <span class="kpi-subtext">Delimiter: <code>{{ $dataset->delimiter ?: ',' }}</code></span>
    </div>
</div>

@if($dataset->status === 'processing')
    <div class="alert alert-warning" style="margin-bottom: 2rem;">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <div>
            <strong>Import & Profiling in progress.</strong> The Python analytics service and chunked MySQL importer are processing this dataset. Refresh in a few moments.
        </div>
    </div>
@elseif($dataset->status === 'failed')
    <div class="alert alert-danger" style="margin-bottom: 2rem;">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <div>
            <strong>Import Failed:</strong>
            {{ $dataset->importJobs->first()?->error_message ?? 'An error occurred during dataset import.' }}
        </div>
    </div>
@endif

<!-- Quality Warnings if any -->
@if(!empty($dataset->analysis_summary['quality']['warnings']))
    <div class="alert alert-info" style="margin-bottom: 2rem;">
        <div>
            <strong>Data Quality Notes:</strong>
            <ul style="margin-left: 1.25rem; font-size: 0.8125rem;">
                @foreach($dataset->analysis_summary['quality']['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<!-- Schema Columns Table -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-header">
        <h2 class="card-title">Inferred Schema & Column Quality</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">{{ $dataset->columns->count() }} columns analyzed</span>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Column Name</th>
                    <th>Type</th>
                    <th>Semantic Role</th>
                    <th>Missing / Nulls</th>
                    <th>Distinct</th>
                    <th>Sample Values</th>
                    <th>Descriptive Statistics</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dataset->columns as $col)
                    <tr>
                        <td style="color: var(--text-light);">{{ $col->ordinal_position + 1 }}</td>
                        <td>
                            <strong style="color: var(--text-main);">{{ $col->display_name }}</strong>
                            <div style="font-size: 0.75rem; font-family: monospace; color: var(--text-muted);">{{ $col->field_key }}</div>
                        </td>
                        <td>
                            @if($col->inferred_type === 'integer')
                                <span class="badge badge-info">Integer</span>
                            @elseif($col->inferred_type === 'decimal')
                                <span class="badge badge-info">Decimal</span>
                            @elseif($col->inferred_type === 'date' || $col->inferred_type === 'datetime')
                                <span class="badge badge-secondary">Date</span>
                            @elseif($col->inferred_type === 'boolean')
                                <span class="badge badge-warning">Boolean</span>
                            @else
                                <span class="badge badge-secondary">String</span>
                            @endif
                        </td>
                        <td>
                            <span style="font-size: 0.8rem; text-transform: uppercase; font-weight: 600; color: var(--text-muted);">
                                {{ $col->semantic_role }}
                            </span>
                        </td>
                        <td>
                            @php
                                $nullPct = $dataset->row_count > 0 ? round(($col->null_count / $dataset->row_count) * 100, 1) : 0;
                            @endphp
                            <span style="{{ $col->null_count > 0 ? 'color: var(--warning); font-weight: 600;' : '' }}">
                                {{ number_format($col->null_count) }}
                            </span>
                            <span style="font-size: 0.75rem; color: var(--text-light);">({{ $nullPct }}%)</span>
                        </td>
                        <td style="font-weight: 500;">
                            {{ $col->distinct_count !== null ? number_format($col->distinct_count) : '—' }}
                        </td>
                        <td style="max-width: 180px;">
                            @if(!empty($col->sample_values))
                                <div style="display: flex; gap: 0.25rem; flex-wrap: wrap;">
                                    @foreach(array_slice($col->sample_values, 0, 3) as $val)
                                        <code style="background: #f1f5f9; padding: 0.1rem 0.35rem; border-radius: 4px; font-size: 0.75rem;">
                                            {{ Str::limit((string)$val, 15) }}
                                        </code>
                                    @endforeach
                                </div>
                            @else
                                <span style="color: var(--text-light);">—</span>
                            @endif
                        </td>
                        <td>
                            @if($col->statistics)
                                <div style="font-size: 0.75rem; line-height: 1.4; color: var(--text-main);">
                                    <div><strong>Min:</strong> {{ $col->statistics['min'] }} &bull; <strong>Max:</strong> {{ $col->statistics['max'] }}</div>
                                    <div><strong>Mean:</strong> {{ $col->statistics['mean'] }} &bull; <strong>Median:</strong> {{ $col->statistics['median'] }}</div>
                                    <div><strong>StdDev:</strong> {{ $col->statistics['stddev'] }}</div>
                                </div>
                            @elseif($col->top_categories)
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    @foreach(array_slice($col->top_categories, 0, 2) as $cat)
                                        <div>{{ Str::limit($cat['value'], 15) }} ({{ $cat['percentage'] }}%)</div>
                                    @endforeach
                                </div>
                            @else
                                <span style="color: var(--text-light);">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Correlation Matrix if numeric columns exist -->
@if(!empty($dataset->analysis_summary['correlations']['columns']))
    <div class="card" style="margin-bottom: 2rem;">
        <div class="card-header">
            <h2 class="card-title">Numeric Pearson Correlation Matrix</h2>
            <span style="font-size: 0.8rem; color: var(--text-muted);">Statistical association across measures (-1.0 to +1.0)</span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Measure</th>
                        @foreach($dataset->analysis_summary['correlations']['columns'] as $c)
                            @php
                                $colMeta = $dataset->columns->firstWhere('field_key', $c);
                            @endphp
                            <th>{{ $colMeta?->display_name ?? $c }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($dataset->analysis_summary['correlations']['matrix'] as $rowIdx => $row)
                        @php
                            $rowKey = $dataset->analysis_summary['correlations']['columns'][$rowIdx] ?? '';
                            $rowMeta = $dataset->columns->firstWhere('field_key', $rowKey);
                        @endphp
                        <tr>
                            <td><strong>{{ $rowMeta?->display_name ?? $rowKey }}</strong></td>
                            @foreach($row as $val)
                                <td style="text-align: center; font-weight: 600; {{ abs($val) > 0.6 ? 'background: #e0e7ff; color: var(--primary);' : '' }}">
                                    {{ number_format($val, 2) }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection

