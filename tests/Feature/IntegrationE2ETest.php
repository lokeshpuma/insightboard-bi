<?php

namespace Tests\Feature;

use App\Jobs\ImportDatasetJob;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Dataset;
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

class IntegrationE2ETest extends TestCase
{
    public function test_complete_end_to_end_data_flow(): void
    {
        // 1. Setup User and Workspace
        $user = User::create([
            'name' => 'E2E Analyst',
            'email' => 'e2e_' . Str::random(5) . '@test.local',
            'password' => Hash::make('password123'),
        ]);

        $workspace = Workspace::create([
            'name' => 'E2E Workspace',
            'slug' => 'e2e-ws-' . Str::lower(Str::random(6)),
            'owner_id' => $user->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // 2. Upload Sample CSV
        $samplePath = database_path('seeders/data/sales_sample.csv');
        $this->assertFileExists($samplePath);

        $uploadedFile = new UploadedFile(
            $samplePath,
            'sales_sample.csv',
            'text/csv',
            null,
            true
        );

        $uploadResponse = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post('/datasets', [
                'name' => 'E2E Q1 Sales',
                'file' => $uploadedFile,
                'delimiter' => ',',
            ]);

        $uploadResponse->assertRedirect();

        $dataset = Dataset::where('workspace_id', $workspace->id)
            ->where('name', 'E2E Q1 Sales')
            ->first();
        $this->assertNotNull($dataset);
        $this->assertContains($dataset->status, ['uploaded', 'ready']);

        $importJob = ImportJob::where('dataset_id', $dataset->id)->first();
        $this->assertNotNull($importJob);

        // 3. Process Import Job with Real Python Microservice
        $analyticsClient = app(AnalyticsServiceClient::class);
        $job = new ImportDatasetJob($dataset->id, $importJob->id);
        $job->handle($analyticsClient);

        $dataset->refresh();
        $this->assertEquals('ready', $dataset->status);
        $this->assertEquals(21, $dataset->row_count);
        $this->assertEquals(7, $dataset->column_count);

        // Check columns created from Python inference
        $regionCol = $dataset->columns->firstWhere('original_name', 'region');
        $revenueCol = $dataset->columns->firstWhere('original_name', 'revenue');
        $this->assertNotNull($regionCol);
        $this->assertNotNull($revenueCol);
        $this->assertEquals('string', $regionCol->inferred_type);
        $this->assertContains($revenueCol->inferred_type, ['decimal', 'integer']);
        $this->assertEquals('measure', $revenueCol->semantic_role);

        // 4. Create Dashboard
        $dashboard = Dashboard::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'E2E Dashboard',
            'visibility' => 'workspace',
        ]);

        // 5. Add Bar Chart Widget
        $widget = DashboardWidget::create([
            'id' => (string) Str::uuid(),
            'dashboard_id' => $dashboard->id,
            'dataset_id' => $dataset->id,
            'title' => 'Revenue by Territory',
            'widget_type' => 'bar',
            'chart_config' => [
                'title' => 'Revenue ($)',
                'chart_type' => 'bar',
                'x_field' => $regionCol->field_key,
                'y_field' => $revenueCol->field_key,
                'aggregation' => 'sum',
            ],
            'width' => 6,
        ]);

        // 6. Query Widget Chart Data
        $chartResponse = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->getJson("/dashboard-widgets/{$widget->id}/data");

        $chartResponse->assertStatus(200);
        $chartData = $chartResponse->json('data');

        $this->assertEquals('bar', $chartData['chart_type']);
        $this->assertContains('North America', $chartData['labels']);
        $this->assertContains('Europe', $chartData['labels']);
        $this->assertNotEmpty($chartData['series'][0]['data']);

        // 7. Verify Overview reflects updated counts
        $overviewResponse = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get('/overview');

        $overviewResponse->assertStatus(200);
        $overviewResponse->assertSee('E2E Q1 Sales');
        $overviewResponse->assertSee('E2E Dashboard');
    }
}
