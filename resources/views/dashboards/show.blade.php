@extends('layouts.app')

@section('title', $dashboard->name)

@section('content')
<div class="page-header">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.25rem;">
            <h1 class="page-title">{{ $dashboard->name }}</h1>
            <span class="badge {{ $dashboard->visibility === 'private' ? 'badge-warning' : 'badge-secondary' }}">
                {{ ucfirst($dashboard->visibility) }}
            </span>
        </div>
        @if($dashboard->description)
            <p class="page-subtitle">{{ $dashboard->description }}</p>
        @endif
    </div>

    <div style="display: flex; gap: 0.75rem;">
        @if(in_array($currentRole, ['owner', 'admin']) || ($currentRole === 'analyst' && $dashboard->created_by === auth()->id()))
            <button type="button" class="btn btn-primary" onclick="openModal('add-widget-modal')">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Widget
            </button>
            <button type="button" class="btn btn-outline" onclick="openModal('edit-dashboard-modal')">
                Edit Details
            </button>
            <form action="{{ route('dashboards.destroy', $dashboard) }}" method="POST" onsubmit="return confirm('Permanently delete this dashboard and all its widgets?');" style="margin: 0;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline" style="color: var(--danger); border-color: #fecaca;">
                    Delete
                </button>
            </form>
        @endif
    </div>
</div>

<!-- Dashboard Filter Bar -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="card-body" style="padding: 1rem;">
        <form id="dashboard-filter-form" style="display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap;">
            <div style="min-width: 180px;">
                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Filter Field</label>
                <select id="dash-filter-col" class="form-select">
                    <option value="">Select column to filter...</option>
                    @foreach($filterableColumns as $col)
                        <option value="{{ $col->field_key }}">{{ $col->display_name }} ({{ $col->inferred_type }})</option>
                    @endforeach
                </select>
            </div>

            <div style="width: 140px;">
                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Operator</label>
                <select id="dash-filter-op" class="form-select">
                    <option value="equals">Equals (=)</option>
                    <option value="contains">Contains</option>
                    <option value="gt">Greater than (&gt;)</option>
                    <option value="lt">Less than (&lt;)</option>
                </select>
            </div>

            <div style="flex: 1; min-width: 180px;">
                <label class="form-label" style="font-size: 0.75rem; margin-bottom: 0.25rem;">Filter Value</label>
                <input type="text" id="dash-filter-val" class="form-control" placeholder="Value to apply across widgets...">
            </div>

            <button type="button" id="apply-filter-btn" class="btn btn-secondary">Apply Dashboard Filter</button>
            <button type="button" id="reset-filter-btn" class="btn btn-outline" style="display: none;">Reset Filters</button>
        </form>
        <div id="active-filter-indicator" style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--primary); display: none;">
            Filtering widgets by: <strong id="filter-summary-text"></strong>
        </div>
    </div>
</div>

<!-- Widgets Container -->
@if($dashboard->widgets->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">📊</div>
            <h3 class="empty-state-title">This dashboard has no widgets yet</h3>
            <p class="empty-state-desc">
                Add KPI cards, bar charts, line trends, or pie distributions from your workspace datasets.
            </p>
            @if(in_array($currentRole, ['owner', 'admin', 'analyst']))
                <button type="button" class="btn btn-primary" onclick="openModal('add-widget-modal')">
                    + Add Your First Widget
                </button>
            @endif
        </div>
    </div>
@else
    <div class="grid grid-cols-12" id="widgets-grid">
        @foreach($dashboard->widgets as $widget)
            @php
                $colSpan = match($widget->width) {
                    12 => 'col-span-12',
                    4 => 'col-span-4',
                    default => 'col-span-6',
                };
            @endphp
            <div class="{{ $colSpan }}" id="widget-card-{{ $widget->id }}">
                <div class="card" style="height: 100%; display: flex; flex-direction: column;">
                    <div class="card-header" style="padding: 0.75rem 1rem;">
                        <div>
                            <strong style="font-size: 0.95rem; color: var(--text-main);">{{ $widget->title }}</strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                {{ $widget->dataset?->name ?? 'Dataset' }} &bull; 
                                <span class="badge badge-secondary" style="font-size: 0.65rem;">{{ ucfirst(str_replace('_', ' ', $widget->widget_type)) }}</span>
                            </div>
                        </div>

                        @if(in_array($currentRole, ['owner', 'admin']) || ($currentRole === 'analyst' && $dashboard->created_by === auth()->id()))
                            <form action="{{ route('dashboards.widgets.destroy', $widget) }}" method="POST" onsubmit="return confirm('Remove widget?');" style="margin: 0;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline btn-sm" style="padding: 0.2rem 0.4rem; color: var(--danger); border-color: transparent;" title="Remove widget">✕</button>
                            </form>
                        @endif
                    </div>

                    <div class="card-body" style="flex: 1; min-height: 280px; position: relative;">
                        <!-- Loading State -->
                        <div class="widget-loader" id="loader-{{ $widget->id }}" style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.8); z-index: 10;">
                            <span style="font-size: 0.85rem; color: var(--text-muted);">Loading widget data...</span>
                        </div>

                        @if($widget->widget_type === 'kpi')
                            <div id="kpi-container-{{ $widget->id }}" style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; min-height: 240px;">
                                <div class="kpi-value" id="kpi-val-{{ $widget->id }}" style="font-size: 3rem;">—</div>
                                <div class="kpi-title" id="kpi-lbl-{{ $widget->id }}">{{ $widget->title }}</div>
                            </div>
                        @else
                            <div style="position: relative; width: 100%; height: 260px;">
                                <canvas id="chart-{{ $widget->id }}"></canvas>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<!-- Modal: Add Widget -->
<div id="add-widget-modal" class="modal-backdrop" style="display: none;">
    <div class="modal-content" style="max-width: 650px;">
        <form action="{{ route('dashboards.widgets.store', $dashboard) }}" method="POST">
            @csrf
            <div class="modal-header">
                <h3 class="card-title">Add Widget to Dashboard</h3>
                <button type="button" class="btn btn-outline btn-sm" data-dismiss="modal">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="widget-title">Widget Title</label>
                    <input type="text" id="widget-title" name="title" class="form-control" placeholder="e.g. Monthly Revenue by Region, Order Counts" required>
                </div>

                <div class="grid grid-cols-2">
                    <div class="form-group">
                        <label class="form-label" for="dataset-select">Source Dataset</label>
                        <select id="dataset-select" name="dataset_id" class="form-select" required>
                            <option value="">Select dataset...</option>
                            @foreach($availableDatasets as $ds)
                                <option value="{{ $ds->id }}">{{ $ds->name }} ({{ number_format($ds->row_count) }} rows)</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="widget-type-select">Visualization Type</label>
                        <select id="widget-type-select" name="widget_type" class="form-select" required>
                            <option value="bar">Bar Chart</option>
                            <option value="horizontal_bar">Horizontal Bar Chart</option>
                            <option value="line">Line Chart</option>
                            <option value="area">Area Chart</option>
                            <option value="pie">Pie Chart</option>
                            <option value="doughnut">Doughnut Chart</option>
                            <option value="scatter">Scatter Chart</option>
                            <option value="kpi">KPI Metric Card</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2">
                    <div class="form-group" id="x-field-group">
                        <label class="form-label" for="x-field-select">Dimension (X-Axis / Category)</label>
                        <select id="x-field-select" name="x_field" class="form-select">
                            <option value="">Select dataset first...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="y-field-select">Measure (Y-Axis / Metric)</label>
                        <select id="y-field-select" name="y_field" class="form-select">
                            <option value="">Select dataset first...</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-3">
                    <div class="form-group">
                        <label class="form-label" for="agg-select">Aggregation</label>
                        <select id="agg-select" name="aggregation" class="form-select" required>
                            <option value="count">Count (Rows)</option>
                            <option value="sum">Sum</option>
                            <option value="avg">Average</option>
                            <option value="distinct_count">Distinct Count</option>
                            <option value="min">Minimum</option>
                            <option value="max">Maximum</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="series-field-select">Group By (Series)</label>
                        <select id="series-field-select" name="series_field" class="form-select">
                            <option value="">None (Single Series)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="width-select">Card Width</label>
                        <select id="width-select" name="width" class="form-select">
                            <option value="6">Half Width (50%)</option>
                            <option value="12">Full Width (100%)</option>
                            <option value="4">One Third (33%)</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Widget</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Dashboard -->
<div id="edit-dashboard-modal" class="modal-backdrop" style="display: none;">
    <div class="modal-content">
        <form action="{{ route('dashboards.update', $dashboard) }}" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-header">
                <h3 class="card-title">Edit Dashboard Details</h3>
                <button type="button" class="btn btn-outline btn-sm" data-dismiss="modal">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="edit-name">Dashboard Title</label>
                    <input type="text" id="edit-name" name="name" class="form-control" value="{{ $dashboard->name }}" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-desc">Description</label>
                    <textarea id="edit-desc" name="description" class="form-control" rows="3">{{ $dashboard->description }}</textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit-visibility">Visibility</label>
                    <select id="edit-visibility" name="visibility" class="form-select">
                        <option value="workspace" {{ $dashboard->visibility === 'workspace' ? 'selected' : '' }}>Workspace</option>
                        <option value="private" {{ $dashboard->visibility === 'private' ? 'selected' : '' }}>Private</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Dataset schemas stored in JSON for dynamic modal dropdown population
const datasetSchemas = {
    @foreach($availableDatasets as $ds)
        "{{ $ds->id }}": [
            @foreach($ds->columns as $col)
                {
                    key: "{{ $col->field_key }}",
                    name: "{{ $col->display_name }}",
                    type: "{{ $col->inferred_type }}",
                    role: "{{ $col->semantic_role }}"
                },
            @endforeach
        ],
    @endforeach
};

const widgetIds = [
    @foreach($dashboard->widgets as $w)
        { id: "{{ $w->id }}", type: "{{ $w->widget_type }}" },
    @endforeach
];

document.addEventListener('DOMContentLoaded', () => {
    // Dynamic dataset column mapping in Add Widget Modal
    const datasetSelect = document.getElementById('dataset-select');
    const xFieldSelect = document.getElementById('x-field-select');
    const yFieldSelect = document.getElementById('y-field-select');
    const seriesFieldSelect = document.getElementById('series-field-select');

    if (datasetSelect) {
        datasetSelect.addEventListener('change', () => {
            const dsId = datasetSelect.value;
            const cols = datasetSchemas[dsId] || [];

            xFieldSelect.innerHTML = '<option value="">None / Auto</option>';
            yFieldSelect.innerHTML = '<option value="">None (Count Rows)</option>';
            seriesFieldSelect.innerHTML = '<option value="">None (Single Series)</option>';

            cols.forEach(c => {
                const optX = document.createElement('option');
                optX.value = c.key;
                optX.textContent = `${c.name} (${c.type})`;
                xFieldSelect.appendChild(optX);

                if (['integer', 'decimal'].includes(c.type)) {
                    const optY = document.createElement('option');
                    optY.value = c.key;
                    optY.textContent = `${c.name} (numeric)`;
                    yFieldSelect.appendChild(optY);
                }

                if (['string', 'boolean'].includes(c.type) || c.role === 'dimension') {
                    const optSeries = document.createElement('option');
                    optSeries.value = c.key;
                    optSeries.textContent = c.name;
                    seriesFieldSelect.appendChild(optSeries);
                }
            });
        });
    }

    // Load widget data
    loadAllWidgets();

    // Dashboard Filter Event Handlers
    const applyFilterBtn = document.getElementById('apply-filter-btn');
    const resetFilterBtn = document.getElementById('reset-filter-btn');
    const filterColSelect = document.getElementById('dash-filter-col');
    const filterOpSelect = document.getElementById('dash-filter-op');
    const filterValInput = document.getElementById('dash-filter-val');
    const activeFilterIndicator = document.getElementById('active-filter-indicator');
    const filterSummaryText = document.getElementById('filter-summary-text');

    if (applyFilterBtn) {
        applyFilterBtn.addEventListener('click', () => {
            const col = filterColSelect.value;
            const val = filterValInput.value.trim();
            if (!col || !val) return;

            const op = filterOpSelect.value;
            const filterParams = {
                filter_col: col,
                filter_op: op,
                filter_val: val
            };

            const colName = filterColSelect.options[filterColSelect.selectedIndex].text;
            filterSummaryText.textContent = `${colName} ${op} "${val}"`;
            activeFilterIndicator.style.display = 'block';
            resetFilterBtn.style.display = 'inline-flex';

            loadAllWidgets(filterParams);
        });
    }

    if (resetFilterBtn) {
        resetFilterBtn.addEventListener('click', () => {
            filterColSelect.value = '';
            filterValInput.value = '';
            activeFilterIndicator.style.display = 'none';
            resetFilterBtn.style.display = 'none';
            loadAllWidgets();
        });
    }
});

function loadAllWidgets(filters = {}) {
    widgetIds.forEach(widget => {
        loadWidget(widget.id, widget.type, filters);
    });
}

function loadWidget(widgetId, widgetType, filters = {}) {
    const loader = document.getElementById(`loader-${widgetId}`);
    if (loader) loader.style.display = 'flex';

    const url = new URL(`/dashboard-widgets/${widgetId}/data`, window.location.origin);
    Object.keys(filters).forEach(k => url.searchParams.append(k, filters[k]));

    fetch(url, {
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(res => res.json())
    .then(json => {
        if (loader) loader.style.display = 'none';
        if (!json.success || !json.data) return;

        const data = json.data;

        if (widgetType === 'kpi') {
            const valEl = document.getElementById(`kpi-val-${widgetId}`);
            if (valEl) {
                valEl.textContent = Number(data.value || 0).toLocaleString();
            }
        } else {
            InsightCharts.render(`chart-${widgetId}`, data);
        }
    })
    .catch(err => {
        if (loader) {
            loader.innerHTML = '<span style="color: var(--danger); font-size: 0.8rem;">Failed to load widget data</span>';
        }
        console.error("Widget fetch error:", err);
    });
}
</script>
@endpush

