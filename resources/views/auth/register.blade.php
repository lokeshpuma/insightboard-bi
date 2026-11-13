@extends('layouts.auth')

@section('title', 'Create Account & Workspace')

@section('content')
<h1 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; text-align: center;">Get Started with InsightBoard</h1>
<p style="font-size: 0.875rem; color: var(--text-muted); text-align: center; margin-bottom: 1.5rem;">Create your account and primary workspace</p>

<form action="{{ route('register') }}" method="POST">
    @csrf

    <div class="form-group">
        <label class="form-label" for="name">Full Name</label>
        <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required autofocus autocomplete="name">
    </div>

    <div class="form-group">
        <label class="form-label" for="email">Work Email</label>
        <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required autocomplete="email">
    </div>

    <div class="form-group">
        <label class="form-label" for="workspace_name">Workspace Name</label>
        <input type="text" id="workspace_name" name="workspace_name" class="form-control" value="{{ old('workspace_name') }}" placeholder="e.g. Acme Analytics, My Team" required>
        <div class="form-text">You'll be assigned as the Workspace Owner.</div>
    </div>

    <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" required autocomplete="new-password">
        <div class="form-text">Minimum 8 characters.</div>
    </div>

    <div class="form-group">
        <label class="form-label" for="password_confirmation">Confirm Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
        Create Account & Workspace
    </button>
</form>

<div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: var(--text-muted);">
    Already have an account? <a href="{{ route('login') }}" style="font-weight: 600;">Sign in</a>
</div>
@endsection

