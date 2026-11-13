@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
<h1 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; text-align: center;">Sign In to InsightBoard</h1>
<p style="font-size: 0.875rem; color: var(--text-muted); text-align: center; margin-bottom: 1.5rem;">Access your datasets, dashboards, and reports</p>

<form action="{{ route('login') }}" method="POST">
    @csrf

    <div class="form-group">
        <label class="form-label" for="email">Work Email</label>
        <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus autocomplete="username">
    </div>

    <div class="form-group">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
            <label class="form-label" for="password" style="margin-bottom: 0;">Password</label>
            <a href="{{ route('password.request') }}" style="font-size: 0.8rem;">Forgot password?</a>
        </div>
        <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
    </div>

    <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
        <input type="checkbox" id="remember" name="remember" style="accent-color: var(--primary);">
        <label for="remember" style="font-size: 0.875rem; color: var(--text-muted); cursor: pointer;">Remember this session</label>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
        Sign In
    </button>
</form>

<div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem; color: var(--text-muted);">
    Don't have an account? <a href="{{ route('register') }}" style="font-weight: 600;">Create workspace account</a>
</div>
@endsection

