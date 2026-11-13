<?php

namespace Tests\Feature;

use App\Jobs\ImportDatasetJob;
use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Models\DatasetRow;
use App\Models\ImportJob;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AnalyticsServiceClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    protected function setupWorkspace(): array
    {
        $user = User::create([
            'name' => 'CSV Tester',
            'email' => 'csv_tester_' . Str::random(5) . '@test.local',
            'password' => Hash::make('password123'),
        ]);

        $workspace = Workspace::create([
            'name' => 'CSV Workspace',
            'slug' => 'csv-ws-' . Str::lower(Str::random(6)),
            'owner_id' => $user->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }

    public function test_rejects_non_csv_file_upload(): void
    {
        [$user, $workspace] = $this->setupWorkspace();

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post('/datasets', [
                'name' => 'Invalid File',
                'file' => $file,
            ]);

        $response->assertSessionHasErrors('file');
    }

    public function test_chunked_import_job_processes_csv_and_is_idempotent(): void
    {
        [$user, $workspace] = $this->setupWorkspace();

        // Create sample CSV content
        $csvContent = "order_id,region,amount\n"
                    . "101,North,150.50\n"
                    . "102,South,220.00\n"
                    . "103,North,95.25\n";

        $datasetId = (string) Str::uuid();
        $storagePath = "datasets/{$datasetId}.csv";
        Storage::disk('local')->put($storagePath, $csvContent);

        $dataset = Dataset::create([
            'id' => $datasetId,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Orders Test',
            'original_filename' => 'orders.csv',
            'storage_path' => $storagePath,
            'file_size_bytes' => strlen($csvContent),
            'file_checksum_sha256' => hash('sha256', $csvContent),
            'delimiter' => ',',
            'encoding' => 'UTF-8',
            'status' => 'uploaded',
        ]);

        $jobRecord = ImportJob::create([
            'id' => (string) Str::uuid(),
            'dataset_id' => $dataset->id,
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'status' => 'queued',
        ]);

        // Run ImportDatasetJob
        $client = app(AnalyticsServiceClient::class);
        $job = new ImportDatasetJob($dataset->id, $jobRecord->id);
        $job->handle($client);

        // Verify dataset status and rows
        $dataset->refresh();
        $jobRecord->refresh();

        $this->assertEquals('ready', $dataset->status);
        $this->assertEquals('completed', $jobRecord->status);
        $this->assertEquals(3, $dataset->row_count);
        $this->assertEquals(3, $dataset->column_count);

        $rowsCount = DatasetRow::where('dataset_id', $dataset->id)->count();
        $this->assertEquals(3, $rowsCount);

        // Check columns created
        $cols = DatasetColumn::where('dataset_id', $dataset->id)->get();
        $this->assertCount(3, $cols);

        // Test Idempotency: Re-running the job should NOT duplicate rows
        $job->handle($client);
        $rowsCountAfter = DatasetRow::where('dataset_id', $dataset->id)->count();
        $this->assertEquals(3, $rowsCountAfter, "Idempotency failed: Row count changed after re-import.");
    }
}

