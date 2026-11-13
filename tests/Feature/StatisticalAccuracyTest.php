<?php

namespace Tests\Feature;

use App\Services\AnalyticsServiceClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StatisticalAccuracyTest extends TestCase
{
    public function test_fallback_profiler_computes_exact_mathematical_metrics(): void
    {
        $client = new AnalyticsServiceClient();

        // Sample CSV with known mathematical values: 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, plus an extreme outlier 1000
        $csvContent = "id,score,category\n"
                    . "1,10,A\n"
                    . "2,20,B\n"
                    . "3,30,A\n"
                    . "4,40,B\n"
                    . "5,50,A\n"
                    . "6,60,B\n"
                    . "7,70,A\n"
                    . "8,80,B\n"
                    . "9,90,A\n"
                    . "10,100,B\n"
                    . "11,1000,A\n"; // outlier

        $tempPath = storage_path('app/testing_stats_' . Str::random(5) . '.csv');
        file_put_contents($tempPath, $csvContent);

        try {
            $result = $client->generateFallbackProfile($tempPath, ',');

            $this->assertTrue($result['success']);
            $this->assertEquals(11, $result['data']['row_count']);
            $this->assertEquals(3, $result['data']['column_count']);

            $scoreCol = collect($result['data']['columns'])->firstWhere('key', 'score');
            $this->assertNotNull($scoreCol);
            $this->assertEquals('integer', $scoreCol['inferred_type']);
            $this->assertEquals('measure', $scoreCol['semantic_role']);

            $stats = $scoreCol['statistics'];
            $this->assertNotNull($stats);

            // True median of sorted [10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 1000] is the 6th element = 60
            $this->assertEquals(60.0, $stats['median']);

            // Min and Max
            $this->assertEquals(10.0, $stats['min']);
            $this->assertEquals(1000.0, $stats['max']);

            // Outlier candidate detected (1000 is > Q3 + 1.5 * IQR)
            $this->assertGreaterThanOrEqual(1, $scoreCol['outlier_candidates_count']);

            // Category frequency distribution
            $catCol = collect($result['data']['columns'])->firstWhere('key', 'category');
            $this->assertNotNull($catCol);
            $this->assertEquals('string', $catCol['inferred_type']);
            $this->assertNotEmpty($catCol['top_categories']);
            $topVal = $catCol['top_categories'][0];
            $this->assertEquals('A', $topVal['value']);
            $this->assertEquals(6, $topVal['count']);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
