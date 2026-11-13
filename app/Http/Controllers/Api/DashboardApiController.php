<?php

namespace App\Http\Controllers\Api;

use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Dataset;
use App\Models\Workspace;
use App\Services\ChartQueryEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class DashboardApiController extends ApiController
{
    public function index(Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);
        $dashboards = $workspace->dashboards()->with('creator')->get();
        return $this->success($dashboards);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('create', [Dashboard::class, $workspace]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'visibility' => ['required', 'in:workspace,private'],
            'layout_config' => ['nullable', 'array'],
        ]);

        $dashboard = Dashboard::create([
            'workspace_id' => $workspace->id,
            'created_by' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'visibility' => $validated['visibility'],
            'layout_config' => $validated['layout_config'] ?? null,
        ]);

        return $this->success($dashboard, 'Dashboard created successfully', 201);
    }

    public function show(Dashboard $dashboard): JsonResponse
    {
        Gate::authorize('view', $dashboard);
        $dashboard->load(['widgets.dataset.columns', 'creator']);
        return $this->success($dashboard);
    }

    public function update(Request $request, Dashboard $dashboard): JsonResponse
    {
        Gate::authorize('update', $dashboard);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'visibility' => ['sometimes', 'required', 'in:workspace,private'],
            'layout_config' => ['nullable', 'array'],
        ]);

        $dashboard->update($validated);

        return $this->success($dashboard, 'Dashboard updated successfully');
    }

    public function destroy(Dashboard $dashboard): JsonResponse
    {
        Gate::authorize('delete', $dashboard);
        $dashboard->widgets()->delete();
        $dashboard->delete();
        return $this->success(null, 'Dashboard deleted successfully');
    }

    public function addWidget(Request $request, Dashboard $dashboard): JsonResponse
    {
        Gate::authorize('update', $dashboard);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'dataset_id' => ['required', 'uuid', 'exists:datasets,id'],
            'widget_type' => ['required', 'in:kpi,bar,horizontal_bar,line,area,pie,doughnut,scatter,table'],
            'chart_config' => ['required', 'array'],
            'width' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $widget = DashboardWidget::create([
            'id' => (string) Str::uuid(),
            'dashboard_id' => $dashboard->id,
            'dataset_id' => $validated['dataset_id'],
            'title' => $validated['title'],
            'widget_type' => $validated['widget_type'],
            'chart_config' => $validated['chart_config'],
            'width' => $validated['width'] ?? 6,
        ]);

        return $this->success($widget, 'Widget added successfully', 201);
    }

    public function updateWidget(Request $request, DashboardWidget $widget): JsonResponse
    {
        Gate::authorize('update', $widget->dashboard);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'chart_config' => ['sometimes', 'required', 'array'],
            'width' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ]);

        $widget->update($validated);

        return $this->success($widget, 'Widget updated successfully');
    }

    public function deleteWidget(DashboardWidget $widget): JsonResponse
    {
        Gate::authorize('update', $widget->dashboard);
        $widget->delete();
        return $this->success(null, 'Widget deleted successfully');
    }

    public function widgetData(Request $request, DashboardWidget $widget, ChartQueryEngine $queryEngine): JsonResponse
    {
        Gate::authorize('view', $widget->dashboard);

        $dataset = $widget->dataset;
        if (!$dataset || !$dataset->isReady()) {
            return $this->error('Dataset is not ready for querying.', [], 422);
        }

        $activeFilters = $request->input('filters', []);
        $data = $queryEngine->query($dataset, $widget->chart_config, $activeFilters);

        return $this->success($data);
    }
}

