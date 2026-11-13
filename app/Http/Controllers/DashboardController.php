<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Dataset;
use App\Models\Workspace;
use App\Services\ChartQueryEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    protected function getActiveWorkspace(Request $request): Workspace
    {
        return $request->attributes->get('currentWorkspace');
    }

    public function index(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('view', $workspace);

        $dashboards = $workspace->dashboards()
            ->with(['creator', 'widgets'])
            ->orderByDesc('updated_at')
            ->paginate(12);

        return view('dashboards.index', compact('workspace', 'dashboards'));
    }

    public function create(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('create', [Dashboard::class, $workspace]);

        $datasets = $workspace->datasets()->where('status', 'ready')->get();

        return view('dashboards.create', compact('workspace', 'datasets'));
    }

    public function store(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('create', [Dashboard::class, $workspace]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'visibility' => ['required', 'in:workspace,private'],
        ]);

        $dashboard = Dashboard::create([
            'workspace_id' => $workspace->id,
            'created_by' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'visibility' => $validated['visibility'],
        ]);

        AuditLog::record($workspace, 'dashboard_created', 'dashboard', $dashboard->id, [
            'name' => $dashboard->name,
        ]);

        return redirect()->route('dashboards.show', $dashboard)
            ->with('success', "Dashboard '{$dashboard->name}' created.");
    }

    public function show(Dashboard $dashboard, Request $request)
    {
        Gate::authorize('view', $dashboard);

        $dashboard->load(['widgets.dataset.columns', 'creator']);
        $workspace = $dashboard->workspace;

        // Collect available filterable fields across all datasets in this dashboard
        $filterableColumns = collect();
        foreach ($dashboard->widgets as $w) {
            if ($w->dataset && $w->dataset->columns) {
                foreach ($w->dataset->columns as $c) {
                    if (!$filterableColumns->contains('field_key', $c->field_key)) {
                        $filterableColumns->push($c);
                    }
                }
            }
        }

        $availableDatasets = $workspace->datasets()->where('status', 'ready')->with('columns')->get();

        return view('dashboards.show', compact('dashboard', 'workspace', 'filterableColumns', 'availableDatasets'));
    }

    public function update(Request $request, Dashboard $dashboard)
    {
        Gate::authorize('update', $dashboard);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'visibility' => ['required', 'in:workspace,private'],
        ]);

        $dashboard->update($validated);

        AuditLog::record($dashboard->workspace, 'dashboard_updated', 'dashboard', $dashboard->id, [
            'name' => $dashboard->name,
        ]);

        return back()->with('success', 'Dashboard details updated.');
    }

    public function destroy(Dashboard $dashboard)
    {
        Gate::authorize('delete', $dashboard);

        $workspace = $dashboard->workspace;
        $name = $dashboard->name;

        DB::transaction(function () use ($dashboard) {
            $dashboard->widgets()->delete();
            $dashboard->delete();
        });

        AuditLog::record($workspace, 'dashboard_deleted', 'dashboard', $dashboard->id, ['name' => $name]);

        return redirect()->route('dashboards.index')->with('success', "Dashboard '{$name}' deleted.");
    }

    public function addWidget(Request $request, Dashboard $dashboard)
    {
        Gate::authorize('update', $dashboard);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'dataset_id' => ['required', 'uuid', 'exists:datasets,id'],
            'widget_type' => ['required', 'in:kpi,bar,horizontal_bar,line,area,pie,doughnut,scatter,table'],
            'x_field' => ['nullable', 'string'],
            'y_field' => ['nullable', 'string'],
            'aggregation' => ['required', 'in:count,distinct_count,sum,avg,min,max'],
            'series_field' => ['nullable', 'string'],
            'width' => ['nullable', 'integer', 'min:3', 'max:12'],
        ]);

        $dataset = Dataset::where('id', $validated['dataset_id'])
            ->where('workspace_id', $dashboard->workspace_id)
            ->firstOrFail();

        $chartConfig = [
            'title' => $validated['title'],
            'chart_type' => $validated['widget_type'],
            'x_field' => $validated['x_field'] ?? null,
            'y_field' => $validated['y_field'] ?? null,
            'aggregation' => $validated['aggregation'],
            'series_field' => $validated['series_field'] ?? null,
            'limit' => 50,
        ];

        $sortOrder = $dashboard->widgets()->count();

        $widget = DashboardWidget::create([
            'id' => (string) Str::uuid(),
            'dashboard_id' => $dashboard->id,
            'dataset_id' => $dataset->id,
            'title' => $validated['title'],
            'widget_type' => $validated['widget_type'],
            'chart_config' => $chartConfig,
            'width' => (int) ($validated['width'] ?? 6),
            'height' => 4,
            'sort_order' => $sortOrder,
        ]);

        AuditLog::record($dashboard->workspace, 'widget_added', 'widget', $widget->id, [
            'dashboard_id' => $dashboard->id,
            'title' => $widget->title,
        ]);

        return redirect()->route('dashboards.show', $dashboard)->with('success', "Widget '{$widget->title}' added.");
    }

    public function deleteWidget(DashboardWidget $widget)
    {
        $dashboard = $widget->dashboard;
        Gate::authorize('update', $dashboard);

        $title = $widget->title;
        $widget->delete();

        AuditLog::record($dashboard->workspace, 'widget_deleted', 'widget', $widget->id, [
            'dashboard_id' => $dashboard->id,
            'title' => $title,
        ]);

        return back()->with('success', "Widget '{$title}' removed.");
    }

    public function widgetData(Request $request, DashboardWidget $widget, ChartQueryEngine $queryEngine)
    {
        Gate::authorize('view', $widget->dashboard);

        $dataset = $widget->dataset;
        if (!$dataset || !$dataset->isReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Dataset is not ready.',
            ], 422);
        }

        // Active filters from query parameters
        $activeFilters = [];
        if ($filterCol = $request->input('filter_col')) {
            $activeFilters[] = [
                'field' => $filterCol,
                'operator' => $request->input('filter_op', 'equals'),
                'value' => $request->input('filter_val'),
            ];
        }

        $result = $queryEngine->query($dataset, $widget->chart_config, $activeFilters);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }
}

