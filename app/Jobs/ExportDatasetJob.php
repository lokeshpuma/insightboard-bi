<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Dataset;
use App\Models\ExportJob;
use App\Models\Report;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;

class ExportDatasetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public string $exportJobId) {}

    public function handle(): void
    {
        $exportJob = ExportJob::find($this->exportJobId);
        if (!$exportJob) return;

        $exportJob->update(['status' => 'processing']);

        try {
            $dataset = null;
            if ($exportJob->resource_type === 'dataset') {
                $dataset = Dataset::findOrFail($exportJob->resource_id);
            } elseif ($exportJob->resource_type === 'report') {
                $report = Report::findOrFail($exportJob->resource_id);
                $datasetId = $report->definition['dataset_id'] ?? null;
                $dataset = Dataset::findOrFail($datasetId);
            }

            if (!$dataset) {
                throw new Exception("Referenced dataset for export could not be loaded.");
            }

            $storageFilename = "exports/{$exportJob->id}.csv";
            $fullDir = storage_path('app/private/exports');
            if (!file_exists($fullDir)) {
                mkdir($fullDir, 0755, true);
            }

            $fullPath = storage_path("app/private/{$storageFilename}");
            $writer = Writer::createFromPath($fullPath, 'w+');

            // Set UTF-8 BOM so Excel opens UTF-8 files correctly
            $writer->setOutputBOM(Writer::BOM_UTF8);

            // Fetch columns for headers
            $columns = $dataset->columns;
            $headers = $columns->pluck('display_name')->all();
            $writer->insertOne($headers);

            // Stream rows in chunks
            $dataset->rows()->orderBy('row_number', 'asc')->chunk(1000, function ($rows) use ($writer, $columns) {
                $records = [];
                foreach ($rows as $row) {
                    $record = [];
                    foreach ($columns as $col) {
                        $val = $row->row_data[$col->field_key] ?? null;
                        $record[] = $this->sanitizeCsvCell($val, $col->isNumeric());
                    }
                    $records[] = $record;
                }
                $writer->insertAll($records);
            });

            $exportJob->update([
                'status' => 'completed',
                'storage_path' => $storageFilename,
                'expires_at' => now()->addDays(7),
            ]);

            AuditLog::record(
                $exportJob->workspace_id,
                'export_completed',
                'export_job',
                $exportJob->id,
                ['resource_type' => $exportJob->resource_type, 'filename' => "{$dataset->name}.csv"],
                $exportJob->requester
            );

            Log::info("Export job completed", ['export_id' => $exportJob->id]);
        } catch (Exception $e) {
            Log::error("Export job failed", ['export_id' => $exportJob->id, 'error' => $e->getMessage()]);
            $exportJob->update([
                'status' => 'failed',
                'error_message' => 'Export generation failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Prevent spreadsheet formula injection (=, +, -, @)
     */
    protected function sanitizeCsvCell(mixed $value, bool $isNumericColumn): mixed
    {
        if ($value === null) {
            return '';
        }

        $str = (string) $value;

        // If it's a numeric column or a valid standard number, don't quote
        if ($isNumericColumn || is_numeric($str)) {
            return $str;
        }

        // Neutralize formula injection characters
        if (in_array(substr($str, 0, 1), ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }

        return $str;
    }
}

