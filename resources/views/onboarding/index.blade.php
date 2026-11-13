@extends('layouts.auth')

@section('title', 'Create Workspace')

@section('content')
<h1 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; text-align: center;">Welcome to InsightBoard</h1>
<p style="font-size: 0.875rem; color: var(--text-muted); text-align: center; margin-bottom: 1.5rem;">To get started, create your first business workspace.</p>

<form action="{{ route('onboarding.store') }}" method="POST">
    @csrf

    <div class="form-group">
        <label class="form-label" for="name">Workspace Name</label>
        <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Acme Marketing, Analytics Core" value="{{ old('name') }}" required autofocus>
        <div class="form-text">Workspaces contain datasets, charts, dashboards, and member permissions.</div>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
        Continue to Workspace →
    </button>
</form>
@endsection

