<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('currentWorkspace');
        Gate::authorize('viewAuditLogs', $workspace);

        $logs = $workspace->auditLogs()
            ->with('actor')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('audit_logs.index', compact('workspace', 'logs'));
    }
}

