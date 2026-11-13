@extends('layouts.app')

@section('title', 'Data Explorer — ' . $dataset->name)

@section('content')
<div class="page-header">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
            <h1 class="page-title">{{ $dataset->name }} — Data Explorer</h1>
            <span class="badge badge-success">{{ number_format($dataset->row_count) }} Records</span>
        </div>
        <p class="page-subtitle">Inspect raw imported rows, apply dynamic filters, and sort columns</p>
    </div>

    <div style="display: flex; gap: 0.75rem;">
        <a href="{{ route('datasets.show', $dataset) }}" class="btn btn-outline">
            ← Schema Profile
        </a>
        <a href="{{ route('dashboards.create') }}?dataset_id={{ $dataset->id }}" class="btn btn-primary">
            + Visualize in Dashboard
        </a>
    </div>
</div>

<!-- Dynamic Filter Bar -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1rem;">
        <form action="{{ route('datasets.explorer', $dataset) }}" method="GET" style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
            <div style="min-width: 180px;">
                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Column</label>
                <select name="filter_col" class="form-select" id="filter-col-select">
                    <option value="">Select column...</option>
                    @foreach($columns as $col)
                        <option value="{{ $col->field_key }}" {{ request('filter_col') === $col->field_key ? 'selected' : '' }}>
                            {{ $col->display_name }} ({{ $col->inferred_type }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="width: 140px;">
                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Operator</label>
                <select name="filter_op" class="form-select">
                    <option value="equals" {{ request('filter_op') === 'equals' ? 'selected' : '' }}>Equals (=)</option>
                    <option value="contains" {{ request('filter_op') === 'contains' ? 'selected' : '' }}>Contains</option>
                    <option value="gt" {{ request('filter_op') === 'gt' ? 'selected' : '' }}>Greater than (&gt;)</option>
                    <option value="lt" {{ request('filter_op') === 'lt' ? 'selected' : '' }}>Less than (&lt;)</option>
                </select>
            </div>

            <div style="flex: 1; min-width: 180px;">
                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Value</label>
                <input type="text" name="filter_val" class="form-control" placeholder="Search or filter value..." value="{{ request('filter_val') }}">
            </div>

            <input type="hidden" name="sort_col" value="{{ request('sort_col') }}">
            <input type="hidden" name="sort_dir" value="{{ request('sort_dir') }}">

            <button type="submit" class="btn btn-secondary">Apply Filter</button>
            @if(request()->hasAny(['filter_col', 'filter_val', 'sort_col']))
                <a href="{{ route('datasets.explorer', $dataset) }}" class="btn btn-outline">Clear</a>
            @endif
        </form>
    </div>
</div>

<!-- Raw Records Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    @foreach($columns as $col)
                        @php
                            $isSorted = request('sort_col') === $col->field_key;
                            $nextDir = ($isSorted && request('sort_dir') === 'asc') ? 'desc' : 'asc';
                            $sortUrl = route('datasets.explorer', array_merge(request()->query(), [
                                'dataset' => $dataset->id,
                                'sort_col' => $col->field_key,
                                'sort_dir' => $nextDir
                            ]));
                        @endphp
                        <th>
                            <a href="{{ $sortUrl }}" style="display: inline-flex; align-items: center; gap: 0.35rem; color: var(--text-main); font-weight: 600;">
                                {{ $col->display_name }}
                                @if($isSorted)
                                    <span style="color: var(--primary);">{{ request('sort_dir') === 'desc' ? '▼' : '▲' }}</span>
                                @else
                                    <span style="color: var(--text-light); font-size: 0.75rem;">⇅</span>
                                @endif
                            </a>
                            <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: normal;">
                                {{ $col->inferred_type }}
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td style="color: var(--text-light); font-size: 0.75rem;">{{ $row->row_number }}</td>
                        @foreach($columns as $col)
                            @php
                                $val = $row->row_data[$col->field_key] ?? null;
                            @endphp
                            <td>
                                @if($val === null)
                                    <span style="color: var(--text-light); font-style: italic;">null</span>
                                @elseif($col->inferred_type === 'decimal')
                                    {{ is_numeric($val) ? number_format((float)$val, 2) : $val }}
                                @elseif($col->inferred_type === 'integer')
                                    {{ is_numeric($val) ? number_format((int)$val) : $val }}
                                @else
                                    {{ Str::limit((string)$val, 50) }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $columns->count() + 1 }}" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                            No rows matched the specified filter criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->hasPages())
        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="font-size: 0.875rem; color: var(--text-muted);">
                Showing {{ $rows->firstItem() ?? 0 }} to {{ $rows->lastItem() ?? 0 }} of {{ number_format($rows->total()) }} records
            </div>
            <div>
                {{ $rows->links() }}
            </div>
        </div>
    @endif
</div>
@endsection

