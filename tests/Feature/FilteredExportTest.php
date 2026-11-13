<?php

namespace Tests\Feature;

use App\Jobs\ExportDatasetJob;
use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Models\DatasetRow;
use App\Models\ExportJob;
use App\Models\Report;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilteredExportTest extends TestCase
{
    public function test_report_export_respects_report_filters(): void
    {
        $user = User::create([
            'name' => 'Report Filter User',
            'email' => 'rf_' . Str::random(5) . '@test.local',
            'password' => Hash::make('password123'),
        ]);

        $ws = Workspace::create([
            'name' => 'Export Workspace',
            'slug' => 'exp-' . Str::lower(Str::random(6)),
            'owner_id' => $user->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $dataset = Dataset::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'name' => 'Filtered Sales',
            'original_filename' => 'sales.csv',
            'storage_path' => 'datasets/filtered.csv',
            'file_size_bytes' => 100,
            'file_checksum_sha256' => 'checksum',
            'delimiter' => ',',
            'encoding' => 'UTF-8',
            'status' => 'ready',
            'row_count' => 4,
            'column_count' => 2,
        ]);

        DatasetColumn::create([
            'dataset_id' => $dataset->id,
            'field_key' => 'region',
            'original_name' => 'Region',
            'display_name' => 'Region',
            'inferred_type' => 'string',
            'semantic_role' => 'dimension',
            'ordinal_position' => 0,
        ]);

        DatasetColumn::create([
            'dataset_id' => $dataset->id,
            'field_key' => 'amount',
            'original_name' => 'Amount',
            'display_name' => 'Amount',
            'inferred_type' => 'decimal',
            'semantic_role' => 'measure',
            'ordinal_position' => 1,
        ]);

        // 2 North records, 2 South records
        DatasetRow::create(['dataset_id' => $dataset->id, 'row_number' => 1, 'row_data' => ['region' => 'North', 'amount' => '100']]);
        DatasetRow::create(['dataset_id' => $dataset->id, 'row_number' => 2, 'row_data' => ['region' => 'North', 'amount' => '200']]);
        DatasetRow::create(['dataset_id' => $dataset->id, 'row_number' => 3, 'row_data' => ['region' => 'South', 'amount' => '300']]);
        DatasetRow::create(['dataset_id' => $dataset->id, 'row_number' => 4, 'row_data' => ['region' => 'South', 'amount' => '400']]);

        // Create Report with filter: region equals North
        $report = Report::create([
            'workspace_id' => $ws->id,
            'created_by' => $user->id,
            'name' => 'North Region Only',
            'definition' => [
                'dataset_id' => $dataset->id,
                'filters' => [
                    ['field' => 'region', 'operator' => 'equals', 'value' => 'North'],
                ],
            ],
        ]);

        $exportJob = ExportJob::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $ws->id,
            'requested_by' => $user->id,
            'resource_type' => 'report',
            'resource_id' => $report->id,
            'export_format' => 'csv',
            'status' => 'queued',
        ]);

        $job = new ExportDatasetJob($exportJob->id);
        $job->handle();

        $exportJob->refresh();
        $this->assertEquals('completed', $exportJob->status);

        $filePath = storage_path("app/private/{$exportJob->storage_path}");
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);

        // Header + 2 North rows = 3 lines total
        $this->assertStringContainsString('North', $content);
        $this->assertStringNotContainsString('South', $content);
    }
}
