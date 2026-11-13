@extends('layouts.auth')

@section('title', 'Set New Password')

@section('content')
<h1 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; text-align: center;">Set New Password</h1>
<p style="font-size: 0.875rem; color: var(--text-muted); text-align: center; margin-bottom: 1.5rem;">Enter your email and choose a new secure password</p>

<form action="{{ route('password.update') }}" method="POST">
    @csrf

    <input type="hidden" name="token" value="{{ $token }}">

    <div class="form-group">
        <label class="form-label" for="email">Account Email</label>
        <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $email) }}" required autofocus>
    </div>

    <div class="form-group">
        <label class="form-label" for="password">New Password</label>
        <input type="password" id="password" name="password" class="form-control" required minlength="8" placeholder="Minimum 8 characters">
    </div>

    <div class="form-group">
        <label class="form-label" for="password_confirmation">Confirm Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required minlength="8" placeholder="Re-enter password">
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
        Reset Password
    </button>
</form>

<div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem;">
    <a href="{{ route('login') }}" style="font-weight: 600;">← Return to Sign In</a>
</div>
@endsection
