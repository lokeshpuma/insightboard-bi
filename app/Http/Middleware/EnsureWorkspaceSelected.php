<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceSelected
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Exclude onboarding and logout from workspace requirement
        if ($request->is('onboarding*') || $request->is('logout') || $request->is('api/v1/workspaces')) {
            return $next($request);
        }

        $workspaces = $user->workspaces()->where('status', 'active')->get();

        if ($workspaces->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active workspace found. Please complete onboarding.',
                ], 403);
            }
            return redirect()->route('onboarding.index');
        }

        $currentWorkspaceId = session('current_workspace_id');
        $currentWorkspace = $workspaces->firstWhere('id', $currentWorkspaceId);

        if (!$currentWorkspace) {
            $currentWorkspace = $workspaces->first();
            session(['current_workspace_id' => $currentWorkspace->id]);
        }

        // Share globally with request and views
        $request->attributes->set('currentWorkspace', $currentWorkspace);
        View::share('currentWorkspace', $currentWorkspace);
        View::share('userWorkspaces', $workspaces);
        View::share('currentRole', $user->roleInWorkspace($currentWorkspace));

        return $next($request);
    }
}

