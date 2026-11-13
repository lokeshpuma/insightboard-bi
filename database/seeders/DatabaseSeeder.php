<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Models\DatasetRow;
use App\Models\ImportJob;
use App\Models\Report;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Reader;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Demo Users
        $owner = User::firstOrCreate(
            ['email' => 'demo@insightboard.local'],
            [
                'name' => 'Demo Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@insightboard.local'],
            [
                'name' => 'Workspace Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $viewer = User::firstOrCreate(
            ['email' => 'viewer@insightboard.local'],
            [
                'name' => 'Dashboard Viewer',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Create Demo Workspace
        $workspace = Workspace::firstOrCreate(
            ['slug' => 'demo-analytics-workspace'],
            [
                'name' => 'Demo Analytics Workspace',
                'owner_id' => $owner->id,
            ]
        );

        WorkspaceMember::firstOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $owner->id],
            ['role' => 'owner', 'status' => 'active', 'joined_at' => now()]
        );

        WorkspaceMember::firstOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $admin->id],
            ['role' => 'admin', 'status' => 'active', 'joined_at' => now()]
        );

        WorkspaceMember::firstOrCreate(
            ['workspace_id' => $workspace->id, 'user_id' => $viewer->id],
            ['role' => 'viewer', 'status' => 'active', 'joined_at' => now()]
        );

        // Ensure storage directory exists
        Storage::disk('local')->makeDirectory('datasets');

        // 3. Seed Datasets
        $demoFiles = [
            [
                'file' => database_path('seeders/data/sales_sample.csv'),
                'name' => 'Q1 Sales & Revenue',
                'filename' => 'sales_sample.csv',
                'types' => [
                    'order_date' => ['date', 'date'],
                    'region' => ['string', 'dimension'],
                    'product' => ['string', 'dimension'],
                    'quantity' => ['integer', 'measure'],
                    'unit_price' => ['decimal', 'measure'],
                    'revenue' => ['decimal', 'measure'],
                    'sales_channel' => ['string', 'dimension'],
                ],
            ],
            [
                'file' => database_path('seeders/data/website_traffic_sample.csv'),
                'name' => 'Website Traffic & Marketing',
                'filename' => 'website_traffic_sample.csv',
                'types' => [
                    'date' => ['date', 'date'],
                    'source' => ['string', 'dimension'],
                    'campaign' => ['string', 'dimension'],
                    'visitors' => ['integer', 'measure'],
                    'sessions' => ['integer', 'measure'],
                    'conversions' => ['integer', 'measure'],
                ],
            ],
            [
                'file' => database_path('seeders/data/support_tickets_sample.csv'),
                'name' => 'Customer Support Tickets',
                'filename' => 'support_tickets_sample.csv',
                'types' => [
                    'created_date' => ['date', 'date'],
                    'category' => ['string', 'dimension'],
                    'priority' => ['string', 'dimension'],
                    'status' => ['string', 'dimension'],
                    'resolution_hours' => ['decimal', 'measure'],
                ],
            ],
        ];

        $seededDatasets = [];

        foreach ($demoFiles as $dInfo) {
            if (!file_exists($dInfo['file'])) continue;

            $datasetId = (string) Str::uuid();
            $storagePath = "datasets/{$datasetId}.csv";
            Storage::disk('local')->put($storagePath, file_get_contents($dInfo['file']));

            $reader = Reader::createFromPath($dInfo['file'], 'r');
            $reader->setHeaderOffset(0);
            $headers = $reader->getHeader();
            $records = iterator_to_array($reader->getRecords());
            $rowCount = count($records);

            $dataset = Dataset::create([
                'id' => $datasetId,
                'workspace_id' => $workspace->id,
                'created_by' => $owner->id,
                'name' => $dInfo['name'],
                'original_filename' => $dInfo['filename'],
                'storage_path' => $storagePath,
                'file_size_bytes' => filesize($dInfo['file']),
                'file_checksum_sha256' => hash_file('sha256', $dInfo['file']),
                'delimiter' => ',',
                'encoding' => 'UTF-8',
                'row_count' => $rowCount,
                'column_count' => count($headers),
                'status' => 'ready',
                'analysis_summary' => [
                    'quality' => [
                        'duplicate_row_count' => 0,
                        'duplicate_row_percentage' => 0.0,
                        'warnings' => [],
                    ],
                    'correlations' => [
                        'columns' => [],
                        'matrix' => [],
                    ],
                    'generated_at' => now()->toIso8601String(),
                ],
                'last_profiled_at' => now(),
            ]);

            $columnMap = [];
            foreach ($headers as $idx => $header) {
                $cleaned = Str::slug($header, '_');
                $fieldKey = "col_{$idx}_{$cleaned}";
                $columnMap[$header] = $fieldKey;

                $typeRole = $dInfo['types'][$header] ?? ['string', 'dimension'];

                DatasetColumn::create([
                    'dataset_id' => $dataset->id,
                    'field_key' => $fieldKey,
                    'original_name' => $header,
                    'display_name' => ucwords(str_replace('_', ' ', $header)),
                    'inferred_type' => $typeRole[0],
                    'semantic_role' => $typeRole[1],
                    'ordinal_position' => $idx,
                    'null_count' => 0,
                    'distinct_count' => count(array_unique(array_column($records, $header))),
                ]);
            }

            // Insert records
            $rowNumber = 1;
            $batch = [];
            foreach ($records as $rec) {
                $rowData = [];
                foreach ($rec as $k => $v) {
                    $key = $columnMap[$k] ?? $k;
                    $trimmed = trim($v);
                    $rowData[$key] = ($trimmed === '' ? null : $trimmed);
                }

                $batch[] = [
                    'dataset_id' => $dataset->id,
                    'row_number' => $rowNumber++,
                    'row_data' => json_encode($rowData),
                    'created_at' => now(),
                ];
            }
            DatasetRow::insert($batch);

            $seededDatasets[$dInfo['filename']] = [
                'dataset' => $dataset,
                'columnMap' => $columnMap,
            ];

            AuditLog::record($workspace, 'dataset_seeded', 'dataset', $dataset->id, ['name' => $dataset->name], $owner);
        }

        // 4. Seed Example Dashboard
        if (isset($seededDatasets['sales_sample.csv'])) {
            $sales = $seededDatasets['sales_sample.csv']['dataset'];
            $map = $seededDatasets['sales_sample.csv']['columnMap'];

            $dashboard = Dashboard::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'created_by' => $owner->id,
                'name' => 'Sales Performance & Analytics',
                'description' => 'Real-time overview of revenue by region, product distributions, and sales channel performance.',
                'visibility' => 'workspace',
            ]);

            // Widget 1: KPI Total Revenue
            DashboardWidget::create([
                'id' => (string) Str::uuid(),
                'dashboard_id' => $dashboard->id,
                'dataset_id' => $sales->id,
                'title' => 'Total Q1 Revenue',
                'widget_type' => 'kpi',
                'chart_config' => [
                    'title' => 'Total Q1 Revenue',
                    'chart_type' => 'kpi',
                    'aggregation' => 'sum',
                    'y_field' => $map['revenue'] ?? 'col_5_revenue',
                ],
                'width' => 4,
                'sort_order' => 1,
            ]);

            // Widget 2: KPI Total Units
            DashboardWidget::create([
                'id' => (string) Str::uuid(),
                'dashboard_id' => $dashboard->id,
                'dataset_id' => $sales->id,
                'title' => 'Total Units Sold',
                'widget_type' => 'kpi',
                'chart_config' => [
                    'title' => 'Total Units Sold',
                    'chart_type' => 'kpi',
                    'aggregation' => 'sum',
                    'y_field' => $map['quantity'] ?? 'col_3_quantity',
                ],
                'width' => 4,
                'sort_order' => 2,
            ]);

            // Widget 3: KPI Transactions
            DashboardWidget::create([
                'id' => (string) Str::uuid(),
                'dashboard_id' => $dashboard->id,
                'dataset_id' => $sales->id,
                'title' => 'Total Transactions',
                'widget_type' => 'kpi',
                'chart_config' => [
                    'title' => 'Total Transactions',
                    'chart_type' => 'kpi',
                    'aggregation' => 'count',
                ],
                'width' => 4,
                'sort_order' => 3,
            ]);

            // Widget 4: Bar Chart - Revenue by Region
            DashboardWidget::create([
                'id' => (string) Str::uuid(),
                'dashboard_id' => $dashboard->id,
                'dataset_id' => $sales->id,
                'title' => 'Revenue by Territory Region',
                'widget_type' => 'bar',
                'chart_config' => [
                    'title' => 'Revenue ($)',
                    'chart_type' => 'bar',
                    'x_field' => $map['region'] ?? 'col_1_region',
                    'y_field' => $map['revenue'] ?? 'col_5_revenue',
                    'aggregation' => 'sum',
                    'sort_direction' => 'desc',
                    'limit' => 20,
                ],
                'width' => 6,
                'sort_order' => 4,
            ]);

            // Widget 5: Doughnut Chart - Revenue by Product
            DashboardWidget::create([
                'id' => (string) Str::uuid(),
                'dashboard_id' => $dashboard->id,
                'dataset_id' => $sales->id,
                'title' => 'Product Revenue Distribution',
                'widget_type' => 'doughnut',
                'chart_config' => [
                    'title' => 'Products',
                    'chart_type' => 'doughnut',
                    'x_field' => $map['product'] ?? 'col_2_product',
                    'y_field' => $map['revenue'] ?? 'col_5_revenue',
                    'aggregation' => 'sum',
                ],
                'width' => 6,
                'sort_order' => 5,
            ]);

            // Widget 6: Line Chart - Revenue by Month
            DashboardWidget::create([
                'id' => (string) Str::uuid(),
                'dashboard_id' => $dashboard->id,
                'dataset_id' => $sales->id,
                'title' => 'Monthly Revenue Trend',
                'widget_type' => 'line',
                'chart_config' => [
                    'title' => 'Revenue ($)',
                    'chart_type' => 'line',
                    'x_field' => $map['order_date'] ?? 'col_0_order_date',
                    'y_field' => $map['revenue'] ?? 'col_5_revenue',
                    'aggregation' => 'sum',
                    'date_granularity' => 'month',
                ],
                'width' => 12,
                'sort_order' => 6,
            ]);

            // 5. Seed Example Report
            Report::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspace->id,
                'created_by' => $owner->id,
                'name' => 'Quarterly Regional Sales Breakdown',
                'description' => 'Standard quarterly report breaking down aggregate revenue and unit sales by geographic region.',
                'definition' => [
                    'dataset_id' => $sales->id,
                    'dataset_name' => $sales->name,
                    'chart_type' => 'bar',
                    'x_field' => $map['region'] ?? 'col_1_region',
                    'y_field' => $map['revenue'] ?? 'col_5_revenue',
                    'aggregation' => 'sum',
                ],
            ]);
        }
    }
}
