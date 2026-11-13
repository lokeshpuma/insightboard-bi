<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'InsightBoard') — Business Intelligence</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/chart.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @stack('styles')
</head>
<body>
<div class="app-layout">
    <!-- Left Sidebar -->
    <aside class="app-sidebar">
        <div class="app-sidebar-brand">
            <img src="{{ asset('favicon.svg') }}" alt="Logo" style="width: 28px; height: 28px; border-radius: 6px;">
            <span>InsightBoard</span>
        </div>

        <nav class="app-sidebar-nav">
            <div class="nav-section-title">Analytics</div>
            <a href="{{ route('overview') }}" class="nav-item {{ request()->routeIs('overview*') ? 'active' : '' }}">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3v18h18M9 9l3 3 5-5 3 3"/></svg>
                Overview
            </a>
            <a href="{{ route('datasets.index') }}" class="nav-item {{ request()->routeIs('datasets*') ? 'active' : '' }}">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7z"/><path d="M9 12h6M9 16h4"/></svg>
                Datasets
            </a>
            <a href="{{ route('dashboards.index') }}" class="nav-item {{ request()->routeIs('dashboards*') ? 'active' : '' }}">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                Dashboards
            </a>
            <a href="{{ route('reports.index') }}" class="nav-item {{ request()->routeIs('reports*') ? 'active' : '' }}">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Reports
            </a>

            @if(isset($currentRole) && in_array($currentRole, ['owner', 'admin']))
                <div class="nav-section-title">Administration</div>
                <a href="{{ route('workspaces.settings') }}" class="nav-item {{ request()->routeIs('workspaces.settings*') ? 'active' : '' }}">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                    Settings & Members
                </a>
                <a href="{{ route('audit-logs.index') }}" class="nav-item {{ request()->routeIs('audit-logs*') ? 'active' : '' }}">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Audit History
                </a>
            @endif
        </nav>

        <div class="app-sidebar-footer">
            <div style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.5rem;">Signed in as</div>
            <div style="font-size: 0.875rem; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                {{ auth()->user()->name }}
            </div>
            <div style="font-size: 0.75rem; color: #cbd5e1;">Role: <span class="badge badge-info">{{ ucfirst($currentRole ?? 'member') }}</span></div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="app-main">
        <!-- Top Navigation -->
        <header class="app-header">
            <div class="header-left">
                <button id="sidebar-toggle" class="btn btn-outline btn-sm" style="display: none;" aria-label="Toggle Navigation">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>

                <!-- Workspace Switcher -->
                <div class="dropdown">
                    <button class="workspace-badge" data-toggle="dropdown" data-target="workspace-menu" type="button">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 21h18M3 7v1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7"/></svg>
                        <span>{{ $currentWorkspace->name ?? 'Workspace' }}</span>
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div id="workspace-menu" class="dropdown-menu">
                        <div style="padding: 0.5rem 1rem; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Workspaces</div>
                        @if(isset($userWorkspaces))
                            @foreach($userWorkspaces as $ws)
                                <form action="{{ route('workspaces.switch', $ws) }}" method="POST" style="margin: 0;">
                                    @csrf
                                    <button type="submit" class="dropdown-item" style="width: 100%; text-align: left; background: none; border: none; cursor: pointer; display: flex; justify-content: space-between;">
                                        <span>{{ $ws->name }}</span>
                                        @if(isset($currentWorkspace) && $currentWorkspace->id === $ws->id)
                                            <span style="color: var(--primary); font-weight: bold;">✓</span>
                                        @endif
                                    </button>
                                </form>
                            @endforeach
                        @endif
                        <hr style="border: none; border-top: 1px solid var(--border-color); margin: 0.25rem 0;">
                        <button type="button" onclick="openModal('new-workspace-modal')" class="dropdown-item" style="width: 100%; text-align: left; background: none; border: none; cursor: pointer; color: var(--primary); font-weight: 600;">
                            + Create Workspace
                        </button>
                    </div>
                </div>
            </div>

            <div class="header-right">
                <!-- User Profile Menu -->
                <div class="dropdown">
                    <button class="btn btn-outline btn-sm" data-toggle="dropdown" data-target="user-menu" type="button" style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="width: 24px; height: 24px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700;">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                        <span>{{ auth()->user()->name }}</span>
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>
                    <div id="user-menu" class="dropdown-menu">
                        <div style="padding: 0.5rem 1rem; font-size: 0.75rem; color: var(--text-muted);">
                            {{ auth()->user()->email }}
                        </div>
                        <a href="{{ route('profile.show') }}" class="dropdown-item">Profile & Password</a>
                        <hr style="border: none; border-top: 1px solid var(--border-color); margin: 0.25rem 0;">
                        <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
                            @csrf
                            <button type="submit" class="dropdown-item" style="width: 100%; text-align: left; background: none; border: none; cursor: pointer; color: var(--danger);">
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="app-content">
            <!-- Flash Messages -->
            @if(session('success'))
                <div class="alert alert-success">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    <div>{{ session('success') }}</div>
                </div>
            @endif

            @if(session('info'))
                <div class="alert alert-info">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <div>{{ session('info') }}</div>
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <div>{{ session('warning') }}</div>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 0.25rem;">Please address the following errors:</div>
                        <ul style="margin-left: 1.25rem; font-size: 0.8125rem;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

<!-- Modal: New Workspace -->
<div id="new-workspace-modal" class="modal-backdrop" style="display: none;">
    <div class="modal-content">
        <form action="{{ route('workspaces.store') }}" method="POST">
            @csrf
            <div class="modal-header">
                <h3 class="card-title">Create New Workspace</h3>
                <button type="button" class="btn btn-outline btn-sm" data-dismiss="modal">✕</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="ws-name">Workspace Name</label>
                    <input type="text" id="ws-name" name="name" class="form-control" placeholder="e.g. Acme Marketing, Q1 Finance" required>
                    <div class="form-text">Workspaces isolate your datasets, dashboards, and reports.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Workspace</button>
            </div>
        </form>
    </div>
</div>

@stack('scripts')
</body>
</html>

