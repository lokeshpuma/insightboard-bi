<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ImportDatasetJob;
use App\Jobs\ReprofileDatasetJob;
use App\Models\AuditLog;
use App\Models\Dataset;
use App\Models\ImportJob;
use App\Models\Workspace;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader;

class DatasetApiController extends ApiController
{
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        $datasets = $workspace->datasets()
            ->with('creator')
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->success($datasets);
    }

    public function store(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('create', [Dataset::class, $workspace]);

        $maxMb = (int) env('CSV_MAX_FILE_SIZE_MB', 50);
        $maxKb = $maxMb * 1024;

        $request->validate([
            'file' => ['required', 'file', "max:{$maxKb}"],
            'name' => ['required', 'string', 'max:150'],
            'delimiter' => ['nullable', 'string', 'max:5'],
        ]);

        $file = $request->file('file');
        $originalFilename = $file->getClientOriginalName();
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext !== 'csv') {
            return $this->error('Only CSV files (.csv) are supported in v1.', [], 422);
        }

        $datasetId = (string) Str::uuid();
        $storagePath = "datasets/{$datasetId}.csv";
        Storage::disk('local')->putFileAs('datasets', $file, "{$datasetId}.csv");

        $result = DB::transaction(function () use ($workspace, $datasetId, $request, $originalFilename, $storagePath, $file) {
            $dataset = Dataset::create([
                'id' => $datasetId,
                'workspace_id' => $workspace->id,
                'created_by' => Auth::id(),
                'name' => $request->input('name'),
                'original_filename' => $originalFilename,
                'storage_path' => $storagePath,
                'file_size_bytes' => $file->getSize(),
                'file_checksum_sha256' => hash_file('sha256', $file->getRealPath()),
                'delimiter' => $request->input('delimiter', ','),
                'encoding' => 'UTF-8',
                'status' => 'uploaded',
            ]);

            $jobId = (string) Str::uuid();
            $job = ImportJob::create([
                'id' => $jobId,
                'dataset_id' => $dataset->id,
                'workspace_id' => $workspace->id,
                'created_by' => Auth::id(),
                'status' => 'queued',
            ]);

            return ['dataset' => $dataset, 'import_job' => $job];
        });

        // Dispatch background queue job after transaction commits
        ImportDatasetJob::dispatch($result['dataset']->id, $result['import_job']->id)->afterCommit();

        return $this->success($result, 'Dataset uploaded and import job queued', 202);
    }

    public function show(Dataset $dataset): JsonResponse
    {
        Gate::authorize('view', $dataset);
        $dataset->load(['columns', 'creator', 'importJobs' => fn($q) => $q->latest()->limit(5)]);
        return $this->success($dataset);
    }

    public function columns(Dataset $dataset): JsonResponse
    {
        Gate::authorize('view', $dataset);
        return $this->success($dataset->columns);
    }

    public function preview(Dataset $dataset): JsonResponse
    {
        Gate::authorize('view', $dataset);
        if ($dataset->status !== 'ready') {
            return $this->error("Dataset is not ready for preview (status: {$dataset->status})", 422);
        }
        $previewRows = $dataset->rows()->orderBy('row_number', 'asc')->limit(10)->get();
        return $this->success([
            'columns' => $dataset->columns,
            'rows' => $previewRows,
        ]);
    }

    public function rows(Dataset $dataset, Request $request): JsonResponse
    {
        Gate::authorize('view', $dataset);
        if ($dataset->status !== 'ready') {
            return $this->error("Dataset is not ready for querying (status: {$dataset->status})", 422);
        }

        $query = $dataset->rows();
        $columnKeys = $dataset->columns->pluck('field_key')->all();

        // Safe parameter filters
        if ($filterCol = $request->input('filter_col')) {
            if (in_array($filterCol, $columnKeys, true)) {
                $val = $request->input('filter_val');
                $op = $request->input('filter_op', 'equals');
                $jsonPath = "$.{$filterCol}";

                if ($op === 'equals') {
                    $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)) = ?", [$jsonPath, $val]);
                } elseif ($op === 'contains') {
                    $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)) LIKE ?", [$jsonPath, "%{$val}%"]);
                }
            }
        }

        $sortCol = $request->input('sort_col');
        $sortDir = strtolower($request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if ($sortCol && in_array($sortCol, $columnKeys, true)) {
            $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)) {$sortDir}", ["$.{$sortCol}"]);
        } else {
            $query->orderBy('row_number', 'asc');
        }

        $limit = min((int) $request->input('per_page', 25), 100);
        $rows = $query->paginate($limit);

        return $this->success($rows);
    }

    public function profile(Dataset $dataset): JsonResponse
    {
        Gate::authorize('view', $dataset);
        return $this->success([
            'dataset_id' => $dataset->id,
            'name' => $dataset->name,
            'status' => $dataset->status,
            'row_count' => $dataset->row_count,
            'column_count' => $dataset->column_count,
            'analysis_summary' => $dataset->analysis_summary,
            'columns' => $dataset->columns,
            'last_profiled_at' => $dataset->last_profiled_at,
        ]);
    }

    public function reprofile(Dataset $dataset): JsonResponse
    {
        Gate::authorize('reprofile', $dataset);
        ReprofileDatasetJob::dispatch($dataset->id);
        return $this->success(['dataset_id' => $dataset->id], 'Reprofile job dispatched', 202);
    }

    public function destroy(Dataset $dataset): JsonResponse
    {
        Gate::authorize('delete', $dataset);

        DB::transaction(function () use ($dataset) {
            $dataset->rows()->delete();
            $dataset->columns()->delete();
            $dataset->delete();
        });

        if (Storage::disk('local')->exists($dataset->storage_path)) {
            Storage::disk('local')->delete($dataset->storage_path);
        }

        return $this->success(null, 'Dataset deleted successfully');
    }

    public function getImportJob(ImportJob $job): JsonResponse
    {
        $dataset = $job->dataset;
        Gate::authorize('view', $dataset);

        return $this->success($job);
    }
}

