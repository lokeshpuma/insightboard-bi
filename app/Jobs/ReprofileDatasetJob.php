<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Dataset;
use App\Models\DatasetColumn;
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

class ReprofileDatasetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $datasetId) {}

    public function handle(AnalyticsServiceClient $analyticsClient): void
    {
        $dataset = Dataset::find($this->datasetId);
        if (!$dataset) return;

        $fullPath = Storage::disk('local')->path($dataset->storage_path);
        if (!file_exists($fullPath)) return;

        try {
            $analysisResult = $analyticsClient->analyze(
                filePath: $fullPath,
                originalFilename: $dataset->original_filename,
                delimiter: $dataset->delimiter ?: ',',
                encoding: $dataset->encoding ?: 'utf-8'
            );

            if ($analysisResult['success']) {
                $profileData = $analysisResult['data'];

                DB::transaction(function () use ($dataset, $profileData) {
                    foreach ($profileData['columns'] as $col) {
                        DatasetColumn::where('dataset_id', $dataset->id)
                            ->where('field_key', $col['key'])
                            ->update([
                                'inferred_type' => $col['inferred_type'],
                                'semantic_role' => $col['semantic_role'],
                                'null_count' => $col['null_count'] ?? 0,
                                'distinct_count' => $col['distinct_count'] ?? null,
                                'statistics' => $col['statistics'] ?? null,
                                'sample_values' => $col['sample_values'] ?? null,
                            ]);
                    }

                    $dataset->update([
                        'analysis_summary' => [
                            'quality' => $profileData['quality'] ?? [],
                            'correlations' => $profileData['correlations'] ?? [],
                            'generated_at' => $profileData['generated_at'] ?? now()->toIso8601String(),
                        ],
                        'last_profiled_at' => now(),
                    ]);
                });

                AuditLog::record(
                    $dataset->workspace_id,
                    'dataset_reprofiled',
                    'dataset',
                    $dataset->id,
                    ['name' => $dataset->name]
                );
            }
        } catch (Exception $e) {
            Log::error("Reprofile dataset failed", ['dataset_id' => $dataset->id, 'error' => $e->getMessage()]);
        }
    }
}

