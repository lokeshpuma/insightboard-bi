<?php

namespace Tests\Feature;

use App\Jobs\ExportDatasetJob;
use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Models\DatasetRow;
use App\Models\ExportJob;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportTest extends TestCase
{
    public function test_csv_export_neutralizes_formula_injection_and_streams_file(): void
    {
        $user = User::create([
            'name' => 'Export User',
            'email' => 'export_' . Str::random(5) . '@test.local',
            'password' => Hash::make('password123'),
        ]);

        $workspace = Workspace::create([
            'name' => 'Export Workspace',
            'slug' => 'exp-ws-' . Str::lower(Str::random(6)),
            'owner_id' => $user->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $dataset = Dataset::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Potentially Malicious CSV',
            'original_filename' => 'payload.csv',
            'storage_path' => 'datasets/payload.csv',
            'file_size_bytes' => 100,
            'file_checksum_sha256' => hash('sha256', 'malicious'),
            'row_count' => 2,
            'column_count' => 2,
            'status' => 'ready',
        ]);

        DatasetColumn::create([
            'dataset_id' => $dataset->id,
            'field_key' => 'col_0_title',
            'original_name' => 'title',
            'display_name' => 'Title',
            'inferred_type' => 'string',
            'semantic_role' => 'dimension',
        ]);

        DatasetColumn::create([
            'dataset_id' => $dataset->id,
            'field_key' => 'col_1_score',
            'original_name' => 'score',
            'display_name' => 'Score',
            'inferred_type' => 'decimal',
            'semantic_role' => 'measure',
        ]);

        // Row 1 contains a formula injection payload in text cell: "=cmd|'/C calc'!A0"
        DatasetRow::create([
            'dataset_id' => $dataset->id,
            'row_number' => 1,
            'row_data' => [
                'col_0_title' => '=cmd|\'/C calc\'!A0',
                'col_1_score' => '10.50',
            ],
        ]);

        // Row 2 contains text starting with "+2" and "@admin"
        DatasetRow::create([
            'dataset_id' => $dataset->id,
            'row_number' => 2,
            'row_data' => [
                'col_0_title' => '@admin_alert',
                'col_1_score' => '-5.25', // Legitimate negative number in numeric column should NOT be corrupted
            ],
        ]);

        $exportJob = ExportJob::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'requested_by' => $user->id,
            'resource_type' => 'dataset',
            'resource_id' => $dataset->id,
            'format' => 'csv',
            'status' => 'pending',
        ]);

        // Execute export job
        $job = new ExportDatasetJob($exportJob->id);
        $job->handle();

        $exportJob->refresh();
        $this->assertEquals('completed', $exportJob->status);
        $this->assertNotNull($exportJob->storage_path);

        $exportedCsvPath = storage_path("app/private/{$exportJob->storage_path}");
        $this->assertFileExists($exportedCsvPath);

        $csvContents = file_get_contents($exportedCsvPath);

        // Verify formula injection neutralized: "=cmd..." prefixed with single quote "'=cmd..."
        $this->assertStringContainsString("'=cmd|'/C calc'!A0", $csvContents);
        $this->assertStringContainsString("'@admin_alert", $csvContents);

        // Verify numeric negative number remains uncorrupted "-5.25"
        $this->assertStringContainsString('-5.25', $csvContents);

        // Test download authorization
        $downloadResponse = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get("/exports/{$exportJob->id}/download");

        $downloadResponse->assertStatus(200);
        $downloadResponse->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
