<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $workspaces = $user->workspaces()->where('status', 'active')->get();
        if ($workspaces->isNotEmpty()) {
            return redirect()->route('overview');
        }

        return view('onboarding.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $user = Auth::user();

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

        session(['current_workspace_id' => $workspace->id]);

        return redirect()->route('overview')->with('success', "Workspace '{$workspace->name}' created successfully!");
    }
}

