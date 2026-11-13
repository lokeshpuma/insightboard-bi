<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyticsServiceClient
{
    protected string $baseUrl;
    protected string $token;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.analytics.url', env('ANALYTICS_SERVICE_URL', 'http://127.0.0.1:8001')), '/');
        $this->token = config('services.analytics.token', env('ANALYTICS_SERVICE_TOKEN', 'insightboard-secret-analytics-token-2026'));
        $this->timeout = (int) config('services.analytics.timeout', env('ANALYTICS_TIMEOUT_SECONDS', 180));
    }

    public function health(): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/internal/v1/health");

            if ($response->successful()) {
                return ['status' => 'healthy', 'data' => $response->json()];
            }

            return ['status' => 'unhealthy', 'code' => $response->status()];
        } catch (Exception $e) {
            Log::warning('Analytics service health check failed', ['message' => $e->getMessage()]);
            return ['status' => 'offline', 'error' => 'Service unreachable'];
        }
    }

    /**
     * Submit CSV file to Python service for profiling
     */
    public function analyze(
        string $filePath,
        string $originalFilename = 'dataset.csv',
        string $delimiter = ',',
        string $encoding = 'utf-8',
        int $maxCategories = 20,
        int $correlationLimit = 10
    ): array {
        if (!file_exists($filePath)) {
            throw new Exception("Source CSV file not found on disk.");
        }

        $fileContent = file_get_contents($filePath);
        if ($fileContent === false || strlen($fileContent) === 0) {
            throw new Exception("Unable to read CSV file content or file is empty.");
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->token)
                ->attach('file', $fileContent, $originalFilename)
                ->post("{$this->baseUrl}/internal/v1/analyze", [
                    'delimiter' => $delimiter,
                    'encoding' => $encoding,
                    'max_categories' => (string) $maxCategories,
                    'correlation_column_limit' => (string) $correlationLimit,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            $errorMessage = $response->json('detail') ?? 'Analytics profiling failed with code ' . $response->status();
            Log::error('Analytics service rejected CSV', [
                'status' => $response->status(),
                'detail' => $errorMessage,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'status_code' => $response->status(),
            ];
        } catch (Exception $e) {
            Log::error('Exception communicating with Python analytics service', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Analytics service is currently unavailable. Please verify service status.',
            ];
        }
    }
}

