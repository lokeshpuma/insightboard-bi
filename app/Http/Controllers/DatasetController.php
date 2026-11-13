<?php

namespace App\Http\Controllers;

use App\Jobs\ImportDatasetJob;
use App\Jobs\ReprofileDatasetJob;
use App\Models\AuditLog;
use App\Models\Dataset;
use App\Models\ImportJob;
use App\Models\Workspace;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader;

class DatasetController extends Controller
{
    protected function getActiveWorkspace(Request $request): Workspace
    {
        return $request->attributes->get('currentWorkspace');
    }

    public function index(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('view', $workspace);

        $query = $workspace->datasets()->with('creator');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('original_filename', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');

        if (in_array($sort, ['name', 'row_count', 'column_count', 'created_at'], true)) {
            $query->orderBy($sort, $direction === 'asc' ? 'asc' : 'desc');
        }

        $datasets = $query->paginate(12)->withQueryString();

        return view('datasets.index', compact('workspace', 'datasets'));
    }

    public function create(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('create', [Dataset::class, $workspace]);

        return view('datasets.create', compact('workspace'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200'], // 50MB max
            'delimiter' => ['nullable', 'string', 'max:5'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== 'csv') {
            return response()->json(['success' => false, 'message' => 'Only .csv files are supported.'], 422);
        }

        try {
            $filePath = $file->getRealPath();
            $detectedDelimiter = $request->input('delimiter');

            if (!$detectedDelimiter) {
                // Auto-detect delimiter from first line
                $firstLine = fgets(fopen($filePath, 'r'));
                $commaCount = substr_count($firstLine, ',');
                $semicolonCount = substr_count($firstLine, ';');
                $tabCount = substr_count($firstLine, "\t");

                if ($semicolonCount > $commaCount && $semicolonCount > $tabCount) {
                    $detectedDelimiter = ';';
                } elseif ($tabCount > $commaCount && $tabCount > $semicolonCount) {
                    $detectedDelimiter = "\t";
                } else {
                    $detectedDelimiter = ',';
                }
            }

            $reader = Reader::createFromPath($filePath, 'r');
            $reader->setDelimiter($detectedDelimiter);
            $reader->setHeaderOffset(0);

            $headers = $reader->getHeader();
            $records = [];
            $count = 0;

            foreach ($reader->getRecords() as $record) {
                $records[] = array_values($record);
                $count++;
                if ($count >= 5) break;
            }

            return response()->json([
                'success' => true,
                'delimiter' => $detectedDelimiter,
                'headers' => $headers,
                'preview_rows' => $records,
                'total_preview_count' => $count,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to preview CSV file: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function store(Request $request)
    {
        $workspace = $this->getActiveWorkspace($request);
        Gate::authorize('create', [Dataset::class, $workspace]);

        $maxMb = (int) env('CSV_MAX_FILE_SIZE_MB', 50);
        $maxKb = $maxMb * 1024;

        $request->validate([
            'file' => ['required', 'file', "max:{$maxKb}"],
            'name' => ['required', 'string', 'max:150'],
            'delimiter' => ['nullable', 'string', 'max:5'],
        ]);

        $uploadedFile = $request->file('file');
        $originalFilename = $uploadedFile->getClientOriginalName();
        $ext = strtolower($uploadedFile->getClientOriginalExtension());

        if ($ext !== 'csv') {
            return back()->withErrors(['file' => 'Only CSV files (.csv) are supported in v1.']);
        }

        $fileSize = $uploadedFile->getSize();
        $fileHash = hash_file('sha256', $uploadedFile->getRealPath());
        $delimiter = $request->input('delimiter', ',');

        // Generate private storage path
        $datasetId = (string) Str::uuid();
        $storagePath = "datasets/{$datasetId}.csv";

        // Store privately
        Storage::disk('local')->putFileAs('datasets', $uploadedFile, "{$datasetId}.csv");

        $dataset = DB::transaction(function () use ($workspace, $datasetId, $request, $originalFilename, $storagePath, $fileSize, $fileHash, $delimiter) {
            $ds = Dataset::create([
                'id' => $datasetId,
                'workspace_id' => $workspace->id,
                'created_by' => Auth::id(),
                'name' => $request->input('name'),
                'original_filename' => $originalFilename,
                'storage_path' => $storagePath,
                'file_size_bytes' => $fileSize,
                'file_checksum_sha256' => $fileHash,
                'delimiter' => $delimiter,
                'encoding' => 'UTF-8',
                'status' => 'uploaded',
            ]);

            $job = ImportJob::create([
                'id' => (string) Str::uuid(),
                'dataset_id' => $ds->id,
                'workspace_id' => $workspace->id,
                'created_by' => Auth::id(),
                'status' => 'queued',
            ]);

            AuditLog::record($workspace, 'dataset_uploaded', 'dataset', $ds->id, [
                'name' => $ds->name,
                'filename' => $originalFilename,
                'size_bytes' => $fileSize,
            ]);

            // Dispatch background queue job
            ImportDatasetJob::dispatch($ds->id, $job->id);

            return $ds;
        });

        return redirect()->route('datasets.show', $dataset)
            ->with('success', "Dataset '{$dataset->name}' uploaded and queued for processing.");
    }

    public function show(Dataset $dataset)
    {
        Gate::authorize('view', $dataset);

        $dataset->load(['columns', 'creator', 'importJobs' => fn($q) => $q->latest()->limit(5)]);

        return view('datasets.show', compact('dataset'));
    }

    public function explorer(Dataset $dataset, Request $request)
    {
        Gate::authorize('view', $dataset);

        $columns = $dataset->columns;
        $columnKeys = $columns->pluck('field_key')->all();

        $query = $dataset->rows();

        // Apply column filter if requested
        $filterCol = $request->input('filter_col');
        $filterOp = $request->input('filter_op');
        $filterVal = $request->input('filter_val');

        if ($filterCol && in_array($filterCol, $columnKeys, true) && $filterVal !== null && $filterVal !== '') {
            $jsonPath = "$.{$filterCol}";
            if ($filterOp === 'equals') {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)) = ?", [$jsonPath, $filterVal]);
            } elseif ($filterOp === 'contains') {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)) LIKE ?", [$jsonPath, "%{$filterVal}%"]);
            } elseif ($filterOp === 'gt') {
                $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)) AS DECIMAL(15,4)) > ?", [$jsonPath, (float)$filterVal]);
            } elseif ($filterOp === 'lt') {
                $query->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)) AS DECIMAL(15,4)) < ?", [$jsonPath, (float)$filterVal]);
            }
        }

        // Sorting
        $sortCol = $request->input('sort_col');
        $sortDir = strtolower($request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        if ($sortCol && in_array($sortCol, $columnKeys, true)) {
            $jsonPath = "$.{$sortCol}";
            $colMeta = $columns->firstWhere('field_key', $sortCol);
            if ($colMeta && $colMeta->isNumeric()) {
                $query->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)) AS DECIMAL(15,4)) {$sortDir}", [$jsonPath]);
            } else {
                $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(row_data, ?)) {$sortDir}", [$jsonPath]);
            }
        } else {
            $query->orderBy('row_number', 'asc');
        }

        $rows = $query->paginate(25)->withQueryString();

        return view('datasets.explorer', compact('dataset', 'columns', 'rows'));
    }

    public function reprofile(Dataset $dataset)
    {
        Gate::authorize('reprofile', $dataset);

        ReprofileDatasetJob::dispatch($dataset->id);

        return back()->with('info', "Profiling job queued for '{$dataset->name}'.");
    }

    public function destroy(Dataset $dataset)
    {
        Gate::authorize('delete', $dataset);

        $workspace = $dataset->workspace;
        $name = $dataset->name;

        DB::transaction(function () use ($dataset) {
            $dataset->rows()->delete();
            $dataset->columns()->delete();
            $dataset->delete();
        });

        // Delete underlying file
        if (Storage::disk('local')->exists($dataset->storage_path)) {
            Storage::disk('local')->delete($dataset->storage_path);
        }

        AuditLog::record($workspace, 'dataset_deleted', 'dataset', $dataset->id, ['name' => $name]);

        return redirect()->route('datasets.index')->with('success', "Dataset '{$name}' has been deleted.");
    }
}

