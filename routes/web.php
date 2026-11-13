<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Guest Routes
Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
});

// Authenticated Routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Onboarding
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    // Workspaces
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('/workspaces/switch/{workspace}', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::get('/workspaces/settings', [WorkspaceController::class, 'settings'])->name('workspaces.settings');
    Route::patch('/workspaces/settings', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::post('/workspaces/invite', [WorkspaceController::class, 'invite'])->name('workspaces.invite');
    Route::patch('/workspaces/members/{member}', [WorkspaceController::class, 'updateMember'])->name('workspaces.members.update');
    Route::delete('/workspaces/members/{member}', [WorkspaceController::class, 'removeMember'])->name('workspaces.members.destroy');

    // Overview
    Route::get('/overview', [OverviewController::class, 'index'])->name('overview');

    // Datasets
    Route::get('/datasets', [DatasetController::class, 'index'])->name('datasets.index');
    Route::get('/datasets/create', [DatasetController::class, 'create'])->name('datasets.create');
    Route::post('/datasets/preview', [DatasetController::class, 'preview'])->name('datasets.preview');
    Route::post('/datasets', [DatasetController::class, 'store'])->name('datasets.store');
    Route::get('/datasets/{dataset}', [DatasetController::class, 'show'])->name('datasets.show');
    Route::get('/datasets/{dataset}/explorer', [DatasetController::class, 'explorer'])->name('datasets.explorer');
    Route::post('/datasets/{dataset}/reprofile', [DatasetController::class, 'reprofile'])->name('datasets.reprofile');
    Route::delete('/datasets/{dataset}', [DatasetController::class, 'destroy'])->name('datasets.destroy');

    // Dashboards
    Route::get('/dashboards', [DashboardController::class, 'index'])->name('dashboards.index');
    Route::get('/dashboards/create', [DashboardController::class, 'create'])->name('dashboards.create');
    Route::post('/dashboards', [DashboardController::class, 'store'])->name('dashboards.store');
    Route::get('/dashboards/{dashboard}', [DashboardController::class, 'show'])->name('dashboards.show');
    Route::patch('/dashboards/{dashboard}', [DashboardController::class, 'update'])->name('dashboards.update');
    Route::delete('/dashboards/{dashboard}', [DashboardController::class, 'destroy'])->name('dashboards.destroy');
    Route::post('/dashboards/{dashboard}/widgets', [DashboardController::class, 'addWidget'])->name('dashboards.widgets.store');
    Route::delete('/dashboard-widgets/{widget}', [DashboardController::class, 'deleteWidget'])->name('dashboards.widgets.destroy');
    Route::get('/dashboard-widgets/{widget}/data', [DashboardController::class, 'widgetData'])->name('dashboards.widgets.data');

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/create', [ReportController::class, 'create'])->name('reports.create');
    Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::delete('/reports/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');

    // Exports
    Route::post('/exports', [ExportController::class, 'store'])->name('exports.store');
    Route::get('/exports/{exportJob}', [ExportController::class, 'show'])->name('exports.show');
    Route::get('/exports/{exportJob}/status', [ExportController::class, 'status'])->name('exports.status');
    Route::get('/exports/{exportJob}/download', [ExportController::class, 'download'])->name('exports.download');

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
});
