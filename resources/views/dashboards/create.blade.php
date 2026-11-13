@extends('layouts.app')

@section('title', 'Create Dashboard')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Create New Dashboard</h1>
        <p class="page-subtitle">Configure dashboard details and prepare for adding analytical widgets</p>
    </div>
    <a href="{{ route('dashboards.index') }}" class="btn btn-outline">← Back to Dashboards</a>
</div>

<div class="grid grid-cols-12">
    <div class="col-span-8">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Dashboard Information</h2>
            </div>
            <div class="card-body">
                <form action="{{ route('dashboards.store') }}" method="POST">
                    @csrf

                    <div class="form-group">
                        <label class="form-label" for="name">Dashboard Name</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Revenue & Product Performance" value="{{ old('name') }}" required autofocus>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3" placeholder="Summary of what this dashboard tracks">{{ old('description') }}</textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="visibility">Visibility</label>
                        <select id="visibility" name="visibility" class="form-select">
                            <option value="workspace">Workspace (Accessible to all members)</option>
                            <option value="private">Private (Only visible to you and admins)</option>
                        </select>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                        <a href="{{ route('dashboards.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create & Build Dashboard →</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

