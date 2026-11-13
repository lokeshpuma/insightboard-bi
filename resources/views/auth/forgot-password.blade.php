@extends('layouts.auth')

@section('title', 'Reset Password')

@section('content')
<h1 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; text-align: center;">Reset Password</h1>
<p style="font-size: 0.875rem; color: var(--text-muted); text-align: center; margin-bottom: 1.5rem;">Enter your email to receive a password reset link</p>

<form action="{{ route('password.email') }}" method="POST">
    @csrf

    <div class="form-group">
        <label class="form-label" for="email">Account Email</label>
        <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
        Send Reset Link
    </button>
</form>

<div style="text-align: center; margin-top: 1.5rem; font-size: 0.875rem;">
    <a href="{{ route('login') }}" style="font-weight: 600;">← Return to Sign In</a>
</div>
@endsection

