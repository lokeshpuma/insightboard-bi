<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ExportDatasetJob;
use App\Models\Dataset;
use App\Models\ExportJob;
use App\Models\Report;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ReportApiController extends ApiController
{
    public function index(Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);
        $reports = $workspace->reports()->with('creator')->get();
        return $this->success($reports);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('create', [Report::class, $workspace]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'definition' => ['required', 'array'],
        ]);

        $report = Report::create([
            'workspace_id' => $workspace->id,
            'created_by' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'definition' => $validated['definition'],
        ]);

        return $this->success($report, 'Report created successfully', 201);
    }

    public function show(Report $report): JsonResponse
    {
        Gate::authorize('view', $report);
        return $this->success($report);
    }

    public function destroy(Report $report): JsonResponse
    {
        Gate::authorize('delete', $report);
        $report->delete();
        return $this->success(null, 'Report deleted successfully');
    }

    public function startExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'resource_type' => ['required', 'in:dataset,report'],
            'resource_id' => ['required', 'string'],
        ]);

        if ($validated['resource_type'] === 'dataset') {
            $resource = Dataset::findOrFail($validated['resource_id']);
        } else {
            $resource = Report::findOrFail($validated['resource_id']);
        }

        Gate::authorize('view', $resource);

        $exportJob = ExportJob::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $resource->workspace_id,
            'requested_by' => Auth::id(),
            'resource_type' => $validated['resource_type'],
            'resource_id' => $resource->id,
            'format' => 'csv',
            'status' => 'pending',
        ]);

        ExportDatasetJob::dispatch($exportJob->id);

        return $this->success([
            'export_id' => $exportJob->id,
            'status' => $exportJob->status,
        ], 'Export job queued', 202);
    }

    public function getExport(ExportJob $exportJob): JsonResponse
    {
        Gate::authorize('view', $exportJob);
        return $this->success($exportJob);
    }
}

