<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Dataset;
use App\Models\Report;
use App\Models\Workspace;
use App\Services\ChartQueryEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    protected function getActiveWorkspace(Request $request): Workspace
    {
        return $request->attributes->get('currentWorkspace');
    }

    public function index(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('view', $workspace);

        $reports = $workspace->reports()->with('creator')->latest()->paginate(12);

        return view('reports.index', compact('workspace', 'reports'));
    }

    public function create(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('create', [Report::class, $workspace]);

        $datasets = $workspace->datasets()->where('status', 'ready')->with('columns')->get();

        return view('reports.create', compact('workspace', 'datasets'));
    }

    public function store(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('create', [Report::class, $workspace]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'dataset_id' => ['required', 'uuid', 'exists:datasets,id'],
            'chart_type' => ['required', 'in:bar,line,pie,table'],
            'x_field' => ['nullable', 'string'],
            'y_field' => ['nullable', 'string'],
            'aggregation' => ['required', 'in:count,distinct_count,sum,avg,min,max'],
        ]);

        $dataset = Dataset::where('id', $validated['dataset_id'])
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        $definition = [
            'dataset_id' => $dataset->id,
            'dataset_name' => $dataset->name,
            'chart_type' => $validated['chart_type'],
            'x_field' => $validated['x_field'] ?? null,
            'y_field' => $validated['y_field'] ?? null,
            'aggregation' => $validated['aggregation'],
        ];

        $report = Report::create([
            'workspace_id' => $workspace->id,
            'created_by' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'definition' => $definition,
        ]);

        AuditLog::record($workspace, 'report_created', 'report', $report->id, ['name' => $report->name]);

        return redirect()->route('reports.show', $report)->with('success', "Report '{$report->name}' saved.");
    }

    public function show(Report $report, ChartQueryEngine $queryEngine)
    {
        Gate::authorize('view', $report);

        $workspace = $report->workspace;
        $datasetId = $report->definition['dataset_id'] ?? null;
        $dataset = Dataset::where('id', $datasetId)->where('workspace_id', $workspace->id)->first();

        $chartData = null;
        $summaryStats = null;
        if ($dataset && $dataset->isReady()) {
            $chartData = $queryEngine->query($dataset, $report->definition);

            if (!empty($chartData['series'][0]['data'])) {
                $nums = array_filter($chartData['series'][0]['data'], fn($v) => is_numeric($v));
                if (count($nums) > 0) {
                    $summaryStats = [
                        'count' => count($nums),
                        'sum' => array_sum($nums),
                        'avg' => round(array_sum($nums) / count($nums), 2),
                        'min' => min($nums),
                        'max' => max($nums),
                    ];
                }
            }
        }

        return view('reports.show', compact('report', 'workspace', 'dataset', 'chartData', 'summaryStats'));
    }

    public function destroy(Report $report)
    {
        Gate::authorize('delete', $report);

        $workspace = $report->workspace;
        $name = $report->name;
        $report->delete();

        AuditLog::record($workspace, 'report_deleted', 'report', $report->id, ['name' => $name]);

        return redirect()->route('reports.index')->with('success', "Report '{$name}' deleted.");
    }
}

