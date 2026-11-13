<?php

namespace App\Services;

use App\Models\Dataset;
use App\Models\DatasetColumn;
use App\Models\DatasetRow;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChartQueryEngine
{
    /**
     * Supported aggregations mapped to SQL expressions
     */
    protected array $allowedAggregations = [
        'count' => 'COUNT(*)',
        'distinct_count' => 'COUNT(DISTINCT %s)',
        'sum' => 'SUM(CAST(%s AS DECIMAL(18,4)))',
        'avg' => 'AVG(CAST(%s AS DECIMAL(18,4)))',
        'min' => 'MIN(CAST(%s AS DECIMAL(18,4)))',
        'max' => 'MAX(CAST(%s AS DECIMAL(18,4)))',
        'median' => 'MEDIAN',
    ];

    /**
     * Execute aggregation query for a widget configuration
     */
    public function query(Dataset $dataset, array $config, array $activeFilters = []): array
    {
        // Require dataset to be successfully processed before analytical querying
        if ($dataset->status !== 'ready') {
            throw new Exception("Dataset '{$dataset->name}' is not ready for analysis (current status: {$dataset->status}).");
        }

        $columns = $dataset->columns->keyBy('field_key');
        $chartType = $config['chart_type'] ?? 'bar';
        $aggregation = strtolower($config['aggregation'] ?? 'count');
        $xFieldKey = $config['x_field'] ?? null;
        $yFieldKey = $config['y_field'] ?? null;
        $seriesFieldKey = $config['series_field'] ?? null;
        $limit = min((int) ($config['limit'] ?? 50), 100);
        $dateGranularity = $config['date_granularity'] ?? 'month';
        $warnings = [];

        // Validate aggregation
        if (!isset($this->allowedAggregations[$aggregation])) {
            $aggregation = 'count';
        }

        // Base query on dataset_rows
        $query = DB::table('dataset_rows')
            ->where('dataset_id', $dataset->id);

        // Apply filters (combined dashboard filters & widget filters)
        $filters = array_merge($config['filters'] ?? [], $activeFilters);
        $this->applyFilters($query, $filters, $columns);

        // Handle KPI card: Single aggregated scalar value
        if ($chartType === 'kpi') {
            if ($aggregation === 'median') {
                $scalar = $this->calculateMedianScalar($query, $yFieldKey, $columns);
            } else {
                $expr = $this->buildAggregationSql($aggregation, $yFieldKey, $columns);
                $scalar = $query->selectRaw("{$expr} as metric_value")->value('metric_value');
            }

            $roundedVal = $scalar !== null ? round((float)$scalar, 2) : 0;
            return [
                'chart_type' => 'kpi',
                'value' => $roundedVal,
                'metric_value' => $roundedVal,
                'formatted_value' => number_format($roundedVal, 2),
                'labels' => ['Total'],
                'series' => [
                    ['name' => $config['title'] ?? 'Metric', 'data' => [$roundedVal]]
                ],
                'meta' => [
                    'aggregation' => $aggregation,
                    'measure_field' => $yFieldKey,
                ],
                'warnings' => $warnings,
            ];
        }

        // Validate dimension field (X-axis)
        $xCol = $columns->get($xFieldKey);
        if (!$xCol && $chartType !== 'kpi') {
            $xCol = $columns->firstWhere('semantic_role', 'dimension') ?? $columns->first();
            if ($xCol) {
                $xFieldKey = $xCol->field_key;
            } else {
                return $this->emptyChartResult($chartType, "No dimension field available.");
            }
        }

        // Handle grouped median aggregation in PHP for exact mathematical calculation
        if ($aggregation === 'median') {
            return $this->computeGroupedMedian($query, $xFieldKey, $yFieldKey, $columns, $limit, $dateGranularity, $chartType, $config, $warnings);
        }

        // Build Dimension Expression (handle Date Granularity if column is date)
        $dimSql = "JSON_UNQUOTE(JSON_EXTRACT(row_data, '$.{$xFieldKey}'))";
        if ($xCol && $xCol->isDate()) {
            $format = match ($dateGranularity) {
                'year' => '%Y',
                'month' => '%Y-%m',
                'week' => '%Y-W%v',
                default => '%Y-%m-%d',
            };
            $dimSql = "COALESCE(DATE_FORMAT(STR_TO_DATE({$dimSql}, '%Y-%m-%d'), '{$format}'), {$dimSql})";
        }

        // Build Measure Expression
        $aggSql = $this->buildAggregationSql($aggregation, $yFieldKey, $columns);

        // Query execution for multi-series charts
        if ($seriesFieldKey && $columns->has($seriesFieldKey)) {
            $seriesSql = "JSON_UNQUOTE(JSON_EXTRACT(row_data, '$.{$seriesFieldKey}'))";
            $results = $query
                ->selectRaw("{$dimSql} as dim_val, {$seriesSql} as series_val, {$aggSql} as metric_val")
                ->whereRaw("{$dimSql} IS NOT NULL")
                ->whereRaw("{$seriesSql} IS NOT NULL")
                ->groupByRaw("dim_val, series_val")
                ->orderBy('dim_val', 'asc')
                ->limit($limit * 5)
                ->get();

            $uniqueLabels = $results->pluck('dim_val')->unique()->values()->all();
            $uniqueSeries = $results->pluck('series_val')->unique()->values()->all();

            $seriesData = [];
            foreach ($uniqueSeries as $sName) {
                $points = [];
                foreach ($uniqueLabels as $lbl) {
                    $row = $results->first(fn($r) => $r->dim_val === $lbl && $r->series_val === $sName);
                    $points[] = $row ? round((float)$row->metric_val, 2) : 0;
                }
                $seriesData[] = [
                    'name' => (string) $sName,
                    'data' => $points,
                ];
            }

            return [
                'chart_type' => $chartType,
                'labels' => $uniqueLabels,
                'series' => $seriesData,
                'meta' => [
                    'aggregation' => $aggregation,
                    'dimension' => $xCol->display_name,
                    'measure' => $columns->get($yFieldKey)?->display_name ?? 'Count',
                    'series_field' => $columns->get($seriesFieldKey)?->display_name,
                ],
                'warnings' => $warnings,
            ];
        }

        // Standard Single-Series Chart
        $sortDir = strtolower($config['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $orderByExpr = ($chartType === 'line' || ($xCol && $xCol->isDate())) ? 'dim_val asc' : "metric_val {$sortDir}";

        $results = $query
            ->selectRaw("{$dimSql} as dim_val, {$aggSql} as metric_val")
            ->whereRaw("{$dimSql} IS NOT NULL")
            ->groupByRaw("dim_val")
            ->orderByRaw($orderByExpr)
            ->limit($limit)
            ->get();

        $labels = [];
        $dataPoints = [];
        foreach ($results as $r) {
            $labels[] = (string) ($r->dim_val ?? 'N/A');
            $dataPoints[] = round((float) ($r->metric_val ?? 0), 2);
        }

        return [
            'chart_type' => $chartType,
            'labels' => $labels,
            'series' => [
                [
                    'name' => $config['title'] ?? ($columns->get($yFieldKey)?->display_name ?? 'Metric'),
                    'data' => $dataPoints,
                ]
            ],
            'meta' => [
                'aggregation' => $aggregation,
                'dimension' => $xCol->display_name,
                'measure' => $columns->get($yFieldKey)?->display_name ?? 'Count',
            ],
            'warnings' => $warnings,
        ];
    }

    protected function calculateMedianScalar($query, ?string $yFieldKey, $columns): float
    {
        if (!$yFieldKey || !$columns->has($yFieldKey)) {
            return 0.0;
        }

        $jsonExtract = "JSON_UNQUOTE(JSON_EXTRACT(row_data, '$.{$yFieldKey}'))";
        $values = (clone $query)
            ->whereNotNull(DB::raw($jsonExtract))
            ->selectRaw("CAST({$jsonExtract} AS DECIMAL(18,4)) as val")
            ->orderBy('val', 'asc')
            ->pluck('val')
            ->filter(fn($v) => is_numeric($v))
            ->map(fn($v) => (float)$v)
            ->values()
            ->all();

        $count = count($values);
        if ($count === 0) return 0.0;
        $mid = intval($count / 2);
        return ($count % 2 === 1) ? $values[$mid] : round(($values[$mid - 1] + $values[$mid]) / 2.0, 4);
    }

    protected function computeGroupedMedian($query, string $xFieldKey, ?string $yFieldKey, $columns, int $limit, string $dateGranularity, string $chartType, array $config, array $warnings): array
    {
        $xCol = $columns->get($xFieldKey);
        $dimSql = "JSON_UNQUOTE(JSON_EXTRACT(row_data, '$.{$xFieldKey}'))";
        if ($xCol && $xCol->isDate()) {
            $format = match ($dateGranularity) {
                'year' => '%Y',
                'month' => '%Y-%m',
                'week' => '%Y-W%v',
                default => '%Y-%m-%d',
            };
            $dimSql = "COALESCE(DATE_FORMAT(STR_TO_DATE({$dimSql}, '%Y-%m-%d'), '{$format}'), {$dimSql})";
        }

        $jsonExtract = "JSON_UNQUOTE(JSON_EXTRACT(row_data, '$.{$yFieldKey}'))";
        $rows = (clone $query)
            ->selectRaw("{$dimSql} as dim_val, CAST({$jsonExtract} AS DECIMAL(18,4)) as metric_val")
            ->whereRaw("{$dimSql} IS NOT NULL")
            ->whereNotNull(DB::raw($jsonExtract))
            ->orderBy('dim_val')
            ->get();

        $groups = [];
        foreach ($rows as $row) {
            $dim = (string) ($row->dim_val ?? 'N/A');
            $groups[$dim][] = (float) $row->metric_val;
        }

        $labels = [];
        $dataPoints = [];
        $countGroup = 0;
        foreach ($groups as $dim => $nums) {
            if ($countGroup++ >= $limit) break;
            sort($nums, SORT_NUMERIC);
            $n = count($nums);
            $mid = intval($n / 2);
            $med = ($n % 2 === 1) ? $nums[$mid] : ($nums[$mid - 1] + $nums[$mid]) / 2.0;

            $labels[] = $dim;
            $dataPoints[] = round($med, 2);
        }

        return [
            'chart_type' => $chartType,
            'labels' => $labels,
            'series' => [
                [
                    'name' => $config['title'] ?? ($columns->get($yFieldKey)?->display_name ?? 'Median'),
                    'data' => $dataPoints,
                ]
            ],
            'meta' => [
                'aggregation' => 'median',
                'dimension' => $xCol->display_name,
                'measure' => $columns->get($yFieldKey)?->display_name ?? 'Median',
            ],
            'warnings' => $warnings,
        ];
    }

    protected function buildAggregationSql(string $aggregation, ?string $yFieldKey, $columns): string
    {
        if ($aggregation === 'count' || !$yFieldKey || !$columns->has($yFieldKey)) {
            return 'COUNT(*)';
        }

        $jsonExtract = "JSON_UNQUOTE(JSON_EXTRACT(row_data, '$.{$yFieldKey}'))";
        $template = $this->allowedAggregations[$aggregation] ?? 'COUNT(*)';

        return sprintf($template, $jsonExtract);
    }

    protected function applyFilters($query, array $filters, $columns): void
    {
        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $op = $filter['operator'] ?? 'equals';
            $val = $filter['value'] ?? null;

            if (!$field || !$columns->has($field) || $val === null || $val === '') {
                continue;
            }

            $jsonExtract = "JSON_UNQUOTE(JSON_EXTRACT(row_data, '$.{$field}'))";
            $colMeta = $columns->get($field);

            if ($colMeta->isNumeric()) {
                $numExpr = "CAST({$jsonExtract} AS DECIMAL(18,4))";
                if ($op === 'equals') {
                    $query->whereRaw("{$numExpr} = ?", [(float)$val]);
                } elseif ($op === 'gt') {
                    $query->whereRaw("{$numExpr} > ?", [(float)$val]);
                } elseif ($op === 'lt') {
                    $query->whereRaw("{$numExpr} < ?", [(float)$val]);
                } elseif ($op === 'between' && is_array($val) && count($val) === 2) {
                    $query->whereRaw("{$numExpr} BETWEEN ? AND ?", [(float)$val[0], (float)$val[1]]);
                }
            } elseif ($colMeta->isDate()) {
                if ($op === 'date_range' && is_array($val) && count($val) === 2) {
                    $query->whereRaw("{$jsonExtract} BETWEEN ? AND ?", [$val[0], $val[1]]);
                } elseif ($op === 'equals') {
                    $query->whereRaw("{$jsonExtract} = ?", [$val]);
                }
            } else {
                if ($op === 'equals') {
                    $query->whereRaw("{$jsonExtract} = ?", [$val]);
                } elseif ($op === 'contains') {
                    $query->whereRaw("{$jsonExtract} LIKE ?", ["%{$val}%"]);
                }
            }
        }
    }

    protected function emptyChartResult(string $chartType, string $warning): array
    {
        return [
            'chart_type' => $chartType,
            'labels' => [],
            'series' => [],
            'meta' => [],
            'warnings' => [$warning],
        ];
    }
}
