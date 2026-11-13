<?php

namespace Tests\Feature;

use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Models\DatasetRow;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    protected function setupDashboardEnvironment(): array
    {
        $user = User::create([
            'name' => 'Dash User',
            'email' => 'dash_' . Str::random(5) . '@test.local',
            'password' => Hash::make('password123'),
        ]);

        $workspace = Workspace::create([
            'name' => 'Dash Workspace',
            'slug' => 'dash-ws-' . Str::lower(Str::random(6)),
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
            'name' => 'Dashboard Dataset',
            'original_filename' => 'data.csv',
            'storage_path' => 'datasets/data.csv',
            'file_size_bytes' => 100,
            'file_checksum_sha256' => hash('sha256', 'sample'),
            'row_count' => 2,
            'column_count' => 2,
            'status' => 'ready',
        ]);

        DatasetColumn::create([
            'dataset_id' => $dataset->id,
            'field_key' => 'col_0_item',
            'original_name' => 'item',
            'display_name' => 'Item',
            'inferred_type' => 'string',
            'semantic_role' => 'dimension',
        ]);

        DatasetColumn::create([
            'dataset_id' => $dataset->id,
            'field_key' => 'col_1_cost',
            'original_name' => 'cost',
            'display_name' => 'Cost',
            'inferred_type' => 'decimal',
            'semantic_role' => 'measure',
        ]);

        DatasetRow::create([
            'dataset_id' => $dataset->id,
            'row_number' => 1,
            'row_data' => ['col_0_item' => 'Item A', 'col_1_cost' => '50.00'],
        ]);

        DatasetRow::create([
            'dataset_id' => $dataset->id,
            'row_number' => 2,
            'row_data' => ['col_0_item' => 'Item B', 'col_1_cost' => '75.00'],
        ]);

        return [$user, $workspace, $dataset];
    }

    public function test_user_can_create_dashboard(): void
    {
        [$user, $workspace] = $this->setupDashboardEnvironment();

        $response = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post('/dashboards', [
                'name' => 'Finance Dashboard',
                'description' => 'Tracks expense and profit margins',
                'visibility' => 'workspace',
            ]);

        $this->assertDatabaseHas('dashboards', [
            'name' => 'Finance Dashboard',
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_user_can_add_widget_and_fetch_chart_data(): void
    {
        [$user, $workspace, $dataset] = $this->setupDashboardEnvironment();

        $dashboard = Dashboard::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => 'Operations Board',
            'visibility' => 'workspace',
        ]);

        // Add Widget
        $widgetResponse = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post("/dashboards/{$dashboard->id}/widgets", [
                'title' => 'Costs by Item',
                'dataset_id' => $dataset->id,
                'widget_type' => 'bar',
                'x_field' => 'col_0_item',
                'y_field' => 'col_1_cost',
                'aggregation' => 'sum',
                'width' => 6,
            ]);

        $widget = DashboardWidget::where('dashboard_id', $dashboard->id)->first();
        $this->assertNotNull($widget);

        // Fetch widget chart data
        $dataResponse = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->getJson("/dashboard-widgets/{$widget->id}/data");

        $dataResponse->assertStatus(200);
        $dataResponse->assertJsonStructure([
            'success',
            'data' => [
                'chart_type',
                'labels',
                'series',
                'meta',
            ]
        ]);

        $json = $dataResponse->json();
        $this->assertContains('Item A', $json['data']['labels']);
        $this->assertContains('Item B', $json['data']['labels']);
    }
}

