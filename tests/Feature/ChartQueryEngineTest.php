<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Models\DatasetRow;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ChartQueryEngine;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChartQueryEngineTest extends TestCase
{
    protected function createTestDatasetWithRows(): array
    {
        $user = User::create([
            'name' => 'Chart Tester',
            'email' => 'chart_' . Str::random(5) . '@test.local',
            'password' => Hash::make('password123'),
        ]);

        $workspace = Workspace::create([
            'name' => 'Chart Workspace',
            'slug' => 'chart-ws-' . Str::lower(Str::random(6)),
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
            'name' => 'Revenue Data',
            'original_filename' => 'revenue.csv',
            'storage_path' => 'datasets/revenue.csv',
            'file_size_bytes' => 500,
            'file_checksum_sha256' => hash('sha256', 'demo'),
            'row_count' => 4,
            'column_count' => 3,
            'status' => 'ready',
        ]);

        DatasetColumn::create([
            'dataset_id' => $dataset->id,
            'field_key' => 'col_0_region',
            'original_name' => 'region',
            'display_name' => 'Region',
            'inferred_type' => 'string',
            'semantic_role' => 'dimension',
            'ordinal_position' => 0,
        ]);

        DatasetColumn::create([
            'dataset_id' => $dataset->id,
            'field_key' => 'col_1_amount',
            'original_name' => 'amount',
            'display_name' => 'Amount',
            'inferred_type' => 'decimal',
            'semantic_role' => 'measure',
            'ordinal_position' => 1,
        ]);

        $rows = [
            ['col_0_region' => 'North', 'col_1_amount' => '100.00'],
            ['col_0_region' => 'North', 'col_1_amount' => '200.00'],
            ['col_0_region' => 'South', 'col_1_amount' => '150.00'],
            ['col_0_region' => 'South', 'col_1_amount' => '50.00'],
        ];

        foreach ($rows as $idx => $r) {
            DatasetRow::create([
                'dataset_id' => $dataset->id,
                'row_number' => $idx + 1,
                'row_data' => $r,
            ]);
        }

        return [$dataset, $user, $workspace];
    }

    public function test_kpi_sum_calculation(): void
    {
        [$dataset] = $this->createTestDatasetWithRows();
        $engine = app(ChartQueryEngine::class);

        $config = [
            'chart_type' => 'kpi',
            'y_field' => 'col_1_amount',
            'aggregation' => 'sum',
        ];

        $res = $engine->query($dataset, $config);

        $this->assertEquals('kpi', $res['chart_type']);
        $this->assertEquals(500.00, $res['value']); // 100 + 200 + 150 + 50 = 500
    }

    public function test_bar_chart_sum_aggregation_by_dimension(): void
    {
        [$dataset] = $this->createTestDatasetWithRows();
        $engine = app(ChartQueryEngine::class);

        $config = [
            'chart_type' => 'bar',
            'x_field' => 'col_0_region',
            'y_field' => 'col_1_amount',
            'aggregation' => 'sum',
        ];

        $res = $engine->query($dataset, $config);

        $this->assertEquals('bar', $res['chart_type']);
        $this->assertContains('North', $res['labels']);
        $this->assertContains('South', $res['labels']);

        $northIdx = array_search('North', $res['labels']);
        $southIdx = array_search('South', $res['labels']);

        // North = 100 + 200 = 300, South = 150 + 50 = 200
        $this->assertEquals(300.0, $res['series'][0]['data'][$northIdx]);
        $this->assertEquals(200.0, $res['series'][0]['data'][$southIdx]);
    }

    public function test_query_safeguards_against_sql_injection(): void
    {
        [$dataset] = $this->createTestDatasetWithRows();
        $engine = app(ChartQueryEngine::class);

        // Malicious column attempt
        $config = [
            'chart_type' => 'bar',
            'x_field' => 'col_0_region; DROP TABLE users;--',
            'y_field' => 'col_1_amount',
            'aggregation' => 'sum',
        ];

        // Should not crash or inject, fallback safely
        $res = $engine->query($dataset, $config);
        $this->assertNotNull($res);
        $this->assertEquals('bar', $res['chart_type']);
    }
}

