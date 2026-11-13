<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Dashboard;
use App\Models\Dataset;
use App\Models\Report;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OverviewController extends Controller
{
    public function index(Request $request)
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('currentWorkspace');
        if (!$workspace) {
            return redirect()->route('onboarding.index');
        }

        Gate::authorize('view', $workspace);

        $datasetCount = $workspace->datasets()->count();
        $totalRows = (int) $workspace->datasets()->where('status', 'ready')->sum('row_count');
        $dashboardCount = $workspace->dashboards()->count();
        $reportCount = $workspace->reports()->count();

        $recentDatasets = $workspace->datasets()
            ->with('creator')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recentDashboards = $workspace->dashboards()
            ->with('creator')
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get();

        $recentAuditLogs = collect();
        if (auth()->user()->hasWorkspaceRole($workspace, ['owner', 'admin'])) {
            $recentAuditLogs = $workspace->auditLogs()
                ->with('actor')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();
        }

        return view('overview.index', compact(
            'workspace',
            'datasetCount',
            'totalRows',
            'dashboardCount',
            'reportCount',
            'recentDatasets',
            'recentDashboards',
            'recentAuditLogs'
        ));
    }
}

