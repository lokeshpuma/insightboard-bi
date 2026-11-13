<?php

namespace App\Http\Controllers;

use App\Jobs\ExportDatasetJob;
use App\Models\AuditLog;
use App\Models\Dataset;
use App\Models\ExportJob;
use App\Models\Report;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportController extends Controller
{
    protected function getActiveWorkspace(Request $request): Workspace
    {
        return $request->attributes->get('currentWorkspace');
    }

    public function store(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('create', [ExportJob::class, $workspace]);

        $validated = $request->validate([
            'resource_type' => ['required', 'in:dataset,report'],
            'resource_id' => ['required', 'string'],
        ]);

        if ($validated['resource_type'] === 'dataset') {
            $resource = Dataset::where('id', $validated['resource_id'])
                ->where('workspace_id', $workspace->id)
                ->firstOrFail();
        } else {
            $resource = Report::where('id', $validated['resource_id'])
                ->where('workspace_id', $workspace->id)
                ->firstOrFail();
        }

        $exportJob = ExportJob::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'requested_by' => Auth::id(),
            'resource_type' => $validated['resource_type'],
            'resource_id' => $resource->id,
            'format' => 'csv',
            'status' => 'pending',
        ]);

        AuditLog::record($workspace, 'export_requested', 'export_job', $exportJob->id, [
            'resource_type' => $validated['resource_type'],
            'resource_id' => $resource->id,
        ]);

        // Dispatch background export job
        ExportDatasetJob::dispatch($exportJob->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'export_id' => $exportJob->id,
                    'status' => $exportJob->status,
                ],
                'message' => 'Export job started successfully.',
            ], 202);
        }

        return redirect()->route('exports.show', $exportJob)->with('success', 'CSV Export generation in progress.');
    }

    public function show(ExportJob $exportJob)
    {
        Gate::authorize('view', $exportJob);

        return view('exports.show', compact('exportJob'));
    }

    public function status(ExportJob $exportJob)
    {
        Gate::authorize('view', $exportJob);

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $exportJob->status,
                'download_url' => $exportJob->status === 'completed' ? route('exports.download', $exportJob) : null,
                'error_message' => $exportJob->error_message,
            ],
        ]);
    }

    public function download(ExportJob $exportJob)
    {
        Gate::authorize('download', $exportJob);

        if ($exportJob->status !== 'completed' || !$exportJob->storage_path) {
            abort(404, 'Export file not ready for download.');
        }

        $fullPath = storage_path("app/private/{$exportJob->storage_path}");

        if (!file_exists($fullPath)) {
            abort(404, 'Export file missing from storage.');
        }

        $filename = "insightboard-export-{$exportJob->id}.csv";

        return response()->download($fullPath, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

