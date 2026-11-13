<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('overview');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            $workspace = $user->workspaces()->where('status', 'active')->first();
            if ($workspace) {
                session(['current_workspace_id' => $workspace->id]);
                AuditLog::record($workspace, 'user_login', 'user', (string) $user->id, ['email' => $user->email], $user);
                return redirect()->intended(route('overview'));
            }

            return redirect()->route('onboarding.index');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('overview');
        }
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'workspace_name' => ['required', 'string', 'max:100'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            $slug = Str::slug($validated['workspace_name']) . '-' . Str::lower(Str::random(5));
            $workspace = Workspace::create([
                'name' => $validated['workspace_name'],
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

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        $workspace = $user->workspaces()->first();
        if ($workspace) {
            session(['current_workspace_id' => $workspace->id]);
        }

        return redirect()->route('overview')->with('success', 'Account and workspace created successfully!');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        $currentWorkspaceId = session('current_workspace_id');
        if ($user && $currentWorkspaceId) {
            AuditLog::record($currentWorkspaceId, 'user_logout', 'user', (string) $user->id, ['email' => $user->email], $user);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'You have been logged out.');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        // Simulating standard password reset notification
        return back()->with('status', 'If an account exists with this email address, a password reset link has been dispatched.');
    }
}

