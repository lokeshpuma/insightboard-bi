<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class WorkspaceApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $workspaces = $request->user()->workspaces()->where('status', 'active')->get();
        return $this->success($workspaces);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user();

        $workspace = DB::transaction(function () use ($user, $validated) {
            $slug = Str::slug($validated['name']) . '-' . Str::lower(Str::random(5));
            $workspace = Workspace::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'owner_id' => $user->id,
            ]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            AuditLog::record($workspace, 'workspace_created', 'workspace', (string) $workspace->id, ['name' => $workspace->name], $user);

            return $workspace;
        });

        return $this->success($workspace, 'Workspace created successfully', 201);
    }

    public function show(Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);
        return $this->success($workspace);
    }

    public function update(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('update', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $workspace->update($validated);

        return $this->success($workspace, 'Workspace updated successfully');
    }

    public function members(Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);
        $members = $workspace->members()->with('user')->get();
        return $this->success($members);
    }

    public function overview(Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        $data = [
            'dataset_count' => $workspace->datasets()->count(),
            'total_rows' => (int) $workspace->datasets()->where('status', 'ready')->sum('row_count'),
            'dashboard_count' => $workspace->dashboards()->count(),
            'report_count' => $workspace->reports()->count(),
        ];

        return $this->success($data);
    }

    public function auditLogs(Workspace $workspace): JsonResponse
    {
        Gate::authorize('viewAuditLogs', $workspace);
        $logs = $workspace->auditLogs()->with('actor')->orderByDesc('created_at')->paginate(50);
        return $this->success($logs);
    }
}

