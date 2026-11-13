<?php

use App\Http\Controllers\Api\ApiTokenController;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\DatasetApiController;
use App\Http\Controllers\Api\ReportApiController;
use App\Http\Controllers\Api\WorkspaceApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'success' => true,
            'message' => 'InsightBoard API v1 is healthy',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    // Public authentication token issue endpoint
    Route::post('/tokens', [ApiTokenController::class, 'issue']);

    // Authenticated API routes
    Route::middleware('auth:sanctum')->group(function () {
        // Token management
        Route::get('/tokens', [ApiTokenController::class, 'index']);
        Route::delete('/tokens', [ApiTokenController::class, 'revokeCurrent']);
        Route::delete('/tokens/{id}', [ApiTokenController::class, 'revoke']);
        // Workspaces
        Route::get('/workspaces', [WorkspaceApiController::class, 'index']);
        Route::post('/workspaces', [WorkspaceApiController::class, 'store']);
        Route::get('/workspaces/{workspace}', [WorkspaceApiController::class, 'show']);
        Route::patch('/workspaces/{workspace}', [WorkspaceApiController::class, 'update']);
        Route::get('/workspaces/{workspace}/members', [WorkspaceApiController::class, 'members']);
        Route::get('/workspaces/{workspace}/overview', [WorkspaceApiController::class, 'overview']);
        Route::get('/workspaces/{workspace}/audit-logs', [WorkspaceApiController::class, 'auditLogs']);

        // Datasets
        Route::get('/workspaces/{workspace}/datasets', [DatasetApiController::class, 'index']);
        Route::post('/workspaces/{workspace}/datasets', [DatasetApiController::class, 'store']);
        Route::get('/datasets/{dataset}', [DatasetApiController::class, 'show']);
        Route::delete('/datasets/{dataset}', [DatasetApiController::class, 'destroy']);
        Route::get('/datasets/{dataset}/columns', [DatasetApiController::class, 'columns']);
        Route::get('/datasets/{dataset}/preview', [DatasetApiController::class, 'preview']);
        Route::get('/datasets/{dataset}/rows', [DatasetApiController::class, 'rows']);
        Route::get('/datasets/{dataset}/profile', [DatasetApiController::class, 'profile']);
        Route::post('/datasets/{dataset}/reprofile', [DatasetApiController::class, 'reprofile']);

        // Import Jobs
        Route::get('/import-jobs/{job}', [DatasetApiController::class, 'getImportJob']);

        // Dashboards
        Route::get('/workspaces/{workspace}/dashboards', [DashboardApiController::class, 'index']);
        Route::post('/workspaces/{workspace}/dashboards', [DashboardApiController::class, 'store']);
        Route::get('/dashboards/{dashboard}', [DashboardApiController::class, 'show']);
        Route::patch('/dashboards/{dashboard}', [DashboardApiController::class, 'update']);
        Route::delete('/dashboards/{dashboard}', [DashboardApiController::class, 'destroy']);
        Route::post('/dashboards/{dashboard}/widgets', [DashboardApiController::class, 'addWidget']);
        Route::post('/dashboards/{dashboard}/reorder', [DashboardApiController::class, 'reorderWidgets']);
        Route::patch('/dashboard-widgets/{widget}', [DashboardApiController::class, 'updateWidget']);
        Route::delete('/dashboard-widgets/{widget}', [DashboardApiController::class, 'deleteWidget']);
        Route::post('/dashboard-widgets/{widget}/data', [DashboardApiController::class, 'widgetData']);

        // Reports & Exports
        Route::get('/workspaces/{workspace}/reports', [ReportApiController::class, 'index']);
        Route::post('/workspaces/{workspace}/reports', [ReportApiController::class, 'store']);
        Route::get('/reports/{report}', [ReportApiController::class, 'show']);
        Route::delete('/reports/{report}', [ReportApiController::class, 'destroy']);
        Route::post('/exports', [ReportApiController::class, 'startExport']);
        Route::get('/exports/{exportJob}', [ReportApiController::class, 'getExport']);
    });
});
