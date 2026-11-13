<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Models\DatasetRow;
use App\Models\ImportJob;
use App\Services\AnalyticsServiceClient;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;
use League\Csv\Statement;

class ImportDatasetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;

    public function __construct(
        public string $datasetId,
        public string $importJobId
    ) {
        $this->afterCommit = true;
    }

    public function handle(AnalyticsServiceClient $analyticsClient): void
    {
        $dataset = Dataset::find($this->datasetId);
        $importJob = ImportJob::find($this->importJobId);

        if (!$dataset || !$importJob) {
            Log::error("ImportDatasetJob aborted: Dataset or ImportJob record missing", [
                'dataset_id' => $this->datasetId,
                'job_id' => $this->importJobId,
            ]);
            return;
        }

        $importJob->update([
            'status' => 'processing',
            'started_at' => now(),
            'progress_percentage' => 5,
        ]);
        $dataset->update(['status' => 'processing']);

        $fullPath = Storage::disk('local')->path($dataset->storage_path);

        if (!file_exists($fullPath)) {
            $this->failJob($dataset, $importJob, "Uploaded source file could not be located in storage.");
            return;
        }

        try {
            // Step 1: Call Python Analytics Service for Profiling
            $analysisResult = $analyticsClient->analyze(
                filePath: $fullPath,
                originalFilename: $dataset->original_filename,
                delimiter: $dataset->delimiter ?: ',',
                encoding: $dataset->encoding ?: 'utf-8'
            );

            if (!$analysisResult['success']) {
                $errorMsg = $analysisResult['error'] ?? 'Analytics profiling failed.';
                $this->failJob($dataset, $importJob, $errorMsg);
                return;
            }

            $profileData = $analysisResult['data'];
            $importJob->update(['progress_percentage' => 30]);

            // Step 2: Idempotent Cleanup of prior data
            DB::transaction(function () use ($dataset) {
                DatasetRow::where('dataset_id', $dataset->id)->delete();
                DatasetColumn::where('dataset_id', $dataset->id)->delete();
            });

            // Step 3: Persist Inferred Schema Columns
            $columnMap = []; // Maps CSV header index / original name to unique field_key
            DB::transaction(function () use ($dataset, $profileData, &$columnMap) {
                foreach ($profileData['columns'] as $idx => $col) {
                    $columnMap[$idx] = $col['key'];

                    DatasetColumn::create([
                        'dataset_id' => $dataset->id,
                        'field_key' => $col['key'],
                        'original_name' => $col['label'],
                        'display_name' => $col['label'],
                        'inferred_type' => $col['inferred_type'],
                        'semantic_role' => $col['semantic_role'],
                        'ordinal_position' => $idx,
                        'null_count' => $col['null_count'] ?? 0,
                        'distinct_count' => $col['distinct_count'] ?? null,
                        'statistics' => $col['statistics'] ?? null,
                        'sample_values' => $col['sample_values'] ?? null,
                    ]);
                }

                $dataset->update([
                    'row_count' => $profileData['row_count'],
                    'column_count' => $profileData['column_count'],
                    'analysis_summary' => [
                        'quality' => $profileData['quality'] ?? [],
                        'correlations' => $profileData['correlations'] ?? [],
                        'generated_at' => $profileData['generated_at'] ?? now()->toIso8601String(),
                    ],
                    'last_profiled_at' => now(),
                ]);
            });

            $importJob->update([
                'progress_percentage' => 50,
                'total_rows' => $profileData['row_count'],
            ]);

            // Step 4: Chunked Import into MySQL dataset_rows using League CSV
            $reader = Reader::createFromPath($fullPath, 'r');
            $reader->setDelimiter($dataset->delimiter ?: ',');
            $reader->setHeaderOffset(0);

            $records = $reader->getRecords();
            $chunkSize = (int) env('CSV_IMPORT_CHUNK_SIZE', 500);

            $batch = [];
            $rowNumber = 1;
            $processed = 0;
            $totalRows = $profileData['row_count'];

            foreach ($records as $record) {
                // Map record using columnMap
                $rowData = [];
                $colIndex = 0;
                foreach ($record as $key => $val) {
                    $fieldKey = $columnMap[$colIndex] ?? "col_{$colIndex}";
                    $trimmed = is_string($val) ? trim($val) : $val;
                    $rowData[$fieldKey] = ($trimmed === '' ? null : $trimmed);
                    $colIndex++;
                }

                $batch[] = [
                    'dataset_id' => $dataset->id,
                    'row_number' => $rowNumber,
                    'row_data' => json_encode($rowData, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                ];

                $rowNumber++;
                $processed++;

                if (count($batch) >= $chunkSize) {
                    DatasetRow::insert($batch);
                    $batch = [];

                    $progress = 50 + (int) (($processed / max(1, $totalRows)) * 45);
                    $importJob->update([
                        'processed_rows' => $processed,
                        'progress_percentage' => min(95, $progress),
                    ]);
                }
            }

            // Insert remaining batch
            if (!empty($batch)) {
                DatasetRow::insert($batch);
            }

            // Step 5: Finalize Success
            $dataset->update(['status' => 'ready']);
            $importJob->update([
                'status' => 'completed',
                'progress_percentage' => 100,
                'processed_rows' => $processed,
                'completed_at' => now(),
            ]);

            AuditLog::record(
                $dataset->workspace_id,
                'dataset_import_completed',
                'dataset',
                $dataset->id,
                ['row_count' => $processed, 'name' => $dataset->name]
            );

            Log::info("Dataset import completed successfully", [
                'dataset_id' => $dataset->id,
                'rows' => $processed,
            ]);
        } catch (Exception $e) {
            Log::error("Dataset import failed with exception", [
                'dataset_id' => $dataset->id,
                'error' => $e->getMessage(),
            ]);
            $this->failJob($dataset, $importJob, "Data import failed: " . $e->getMessage());
        }
    }

    protected function failJob(Dataset $dataset, ImportJob $importJob, string $safeMessage): void
    {
        // Purge any partially imported records to prevent corrupted or dirty state
        try {
            DB::transaction(function () use ($dataset) {
                DatasetRow::where('dataset_id', $dataset->id)->delete();
                DatasetColumn::where('dataset_id', $dataset->id)->delete();
            });
        } catch (\Throwable $e) {
            Log::warning("Failed to clean up partial records during job failure", [
                'dataset_id' => $dataset->id,
                'error' => $e->getMessage(),
            ]);
        }

        $dataset->update([
            'status' => 'failed',
            'row_count' => 0,
            'column_count' => 0,
        ]);

        $importJob->update([
            'status' => 'failed',
            'error_message' => $safeMessage,
            'completed_at' => now(),
        ]);

        AuditLog::record(
            $dataset->workspace_id,
            'dataset_import_failed',
            'dataset',
            $dataset->id,
            ['error' => $safeMessage]
        );
    }
}

