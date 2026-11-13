@extends('layouts.app')

@section('title', 'Create Report')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Build Saved Report</h1>
        <p class="page-subtitle">Configure an analytical report with chart visualization and export capability</p>
    </div>
    <a href="{{ route('reports.index') }}" class="btn btn-outline">← Back to Reports</a>
</div>

<div class="grid grid-cols-12">
    <div class="col-span-8">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Report Definition</h2>
            </div>
            <div class="card-body">
                <form action="{{ route('reports.store') }}" method="POST">
                    @csrf

                    <div class="form-group">
                        <label class="form-label" for="name">Report Name</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Monthly Revenue Summary" value="{{ old('name') }}" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Description (Optional)</label>
                        <textarea id="description" name="description" class="form-control" rows="2" placeholder="Describe the report's purpose">{{ old('description') }}</textarea>
                    </div>

                    <div class="grid grid-cols-2">
                        <div class="form-group">
                            <label class="form-label" for="dataset-select">Dataset</label>
                            <select id="dataset-select" name="dataset_id" class="form-select" required>
                                <option value="">Select source dataset...</option>
                                @foreach($datasets as $ds)
                                    <option value="{{ $ds->id }}">{{ $ds->name }} ({{ number_format($ds->row_count) }} rows)</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="chart-type">Visualization Type</label>
                            <select id="chart-type" name="chart_type" class="form-select" required>
                                <option value="bar">Bar Chart</option>
                                <option value="line">Line Trend</option>
                                <option value="pie">Pie Breakdown</option>
                                <option value="table">Data Summary Table</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-3">
                        <div class="form-group">
                            <label class="form-label" for="x-field">Dimension (Group By)</label>
                            <select id="x-field" name="x_field" class="form-select">
                                <option value="">Select dataset first...</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="y-field">Measure Field</label>
                            <select id="y-field" name="y_field" class="form-select">
                                <option value="">Select dataset first...</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="aggregation">Aggregation</label>
                            <select id="aggregation" name="aggregation" class="form-select" required>
                                <option value="count">Count Rows</option>
                                <option value="sum">Sum</option>
                                <option value="avg">Average</option>
                                <option value="distinct_count">Distinct Count</option>
                                <option value="min">Minimum</option>
                                <option value="max">Maximum</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                        <a href="{{ route('reports.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Report →</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const reportDatasets = {
    @foreach($datasets as $ds)
        "{{ $ds->id }}": [
            @foreach($ds->columns as $col)
                { key: "{{ $col->field_key }}", name: "{{ $col->display_name }}", type: "{{ $col->inferred_type }}" },
            @endforeach
        ],
    @endforeach
};

document.addEventListener('DOMContentLoaded', () => {
    const dsSelect = document.getElementById('dataset-select');
    const xSelect = document.getElementById('x-field');
    const ySelect = document.getElementById('y-field');

    dsSelect.addEventListener('change', () => {
        const dsId = dsSelect.value;
        const cols = reportDatasets[dsId] || [];

        xSelect.innerHTML = '<option value="">None / Default</option>';
        ySelect.innerHTML = '<option value="">None (Count Rows)</option>';

        cols.forEach(c => {
            const optX = document.createElement('option');
            optX.value = c.key;
            optX.textContent = `${c.name} (${c.type})`;
            xSelect.appendChild(optX);

            if (['integer', 'decimal'].includes(c.type)) {
                const optY = document.createElement('option');
                optY.value = c.key;
                optY.textContent = `${c.name} (numeric)`;
                ySelect.appendChild(optY);
            }
        });
    });
});
</script>
@endpush

