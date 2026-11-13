@extends('layouts.app')

@section('title', 'Upload Dataset')

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Upload CSV Dataset</h1>
        <p class="page-subtitle">Upload and parse your tabular data for statistical profiling and dashboard creation</p>
    </div>
    <a href="{{ route('datasets.index') }}" class="btn btn-outline">← Back to Datasets</a>
</div>

<div class="grid grid-cols-12">
    <div class="col-span-8">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Dataset Details & File Selection</h2>
            </div>
            <div class="card-body">
                <form id="upload-form" action="{{ route('datasets.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="form-group">
                        <label class="form-label" for="name">Dataset Name</label>
                        <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Q4 Sales Transactions, Support Tickets 2026" value="{{ old('name') }}" required>
                        <div class="form-text">Choose a descriptive name for identification across dashboards.</div>
                    </div>

                    <div class="grid grid-cols-2">
                        <div class="form-group">
                            <label class="form-label" for="delimiter">Column Delimiter</label>
                            <select id="delimiter" name="delimiter" class="form-select">
                                <option value=",">Comma (,)</option>
                                <option value=";">Semicolon (;)</option>
                                <option value="&#9;">Tab (\t)</option>
                                <option value="|">Pipe (|)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Supported Encoding</label>
                            <input type="text" class="form-control" value="UTF-8 / UTF-8 BOM" readonly disabled style="background: #f8fafc;">
                        </div>
                    </div>

                    <!-- Dropzone -->
                    <div class="form-group">
                        <label class="form-label">CSV File</label>
                        <div id="dropzone" style="border: 2px dashed var(--border-color); border-radius: var(--radius-lg); padding: 2.5rem 1.5rem; text-align: center; background: #fafafa; cursor: pointer; transition: all 0.15s ease;">
                            <input type="file" id="file" name="file" accept=".csv" required style="display: none;">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem; color: var(--text-light);">📄</div>
                            <div style="font-weight: 600; color: var(--text-main); margin-bottom: 0.25rem;">
                                <span style="color: var(--primary);">Click to browse</span> or drag and drop your CSV here
                            </div>
                            <div id="file-info" style="font-size: 0.8125rem; color: var(--text-muted);">
                                Maximum file size: {{ env('CSV_MAX_FILE_SIZE_MB', 50) }} MB. UTF-8 encoded CSV.
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                        <button type="button" id="preview-btn" class="btn btn-secondary" style="display: none;">
                            Preview Data
                        </button>
                        <button type="submit" id="submit-btn" class="btn btn-primary" disabled>
                            Upload & Start Import →
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Live Preview Area -->
        <div id="preview-card" class="card" style="margin-top: 1.5rem; display: none;">
            <div class="card-header">
                <h3 class="card-title">Pre-Import Data Preview (First 5 Rows)</h3>
                <span id="detected-delimiter-badge" class="badge badge-info"></span>
            </div>
            <div class="table-responsive">
                <table class="table" id="preview-table">
                    <thead id="preview-thead"></thead>
                    <tbody id="preview-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Guidelines Panel -->
    <div class="col-span-4">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Import Guidelines</h2>
            </div>
            <div class="card-body" style="font-size: 0.875rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <strong style="color: var(--text-main);">Header Row:</strong>
                    The first row must contain column headers. Duplicate or blank headers will be normalized safely.
                </div>
                <div>
                    <strong style="color: var(--text-main);">Safe Ingestion:</strong>
                    Files are stored in private encrypted application storage. Imported rows are stored as chunked records in MySQL.
                </div>
                <div>
                    <strong style="color: var(--text-main);">Data Types:</strong>
                    The Python analytics service automatically classifies columns into measures (numeric), dimensions (text/boolean), and dates.
                </div>
                <div>
                    <strong style="color: var(--text-main);">Configured Limits:</strong>
                    <ul style="margin-left: 1.25rem; margin-top: 0.35rem; font-size: 0.8rem;">
                        <li>Max file size: 50 MB</li>
                        <li>Max records: 100,000 rows</li>
                        <li>Max columns: 100 columns</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('file');
    const fileInfo = document.getElementById('file-info');
    const nameInput = document.getElementById('name');
    const delimiterSelect = document.getElementById('delimiter');
    const submitBtn = document.getElementById('submit-btn');
    const previewBtn = document.getElementById('preview-btn');
    const previewCard = document.getElementById('preview-card');

    dropzone.addEventListener('click', () => fileInput.click());

    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.style.borderColor = 'var(--primary)';
        dropzone.style.background = 'var(--primary-light)';
    });

    dropzone.addEventListener('dragleave', () => {
        dropzone.style.borderColor = 'var(--border-color)';
        dropzone.style.background = '#fafafa';
    });

    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.style.borderColor = 'var(--border-color)';
        dropzone.style.background = '#fafafa';
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            handleFileSelected();
        }
    });

    fileInput.addEventListener('change', handleFileSelected);

    function handleFileSelected() {
        if (!fileInput.files || fileInput.files.length === 0) return;
        const file = fileInput.files[0];
        fileInfo.innerHTML = `<strong style="color: var(--text-main);">${file.name}</strong> (${(file.size / 1024).toFixed(1)} KB)`;
        
        // Auto-fill dataset name if empty
        if (!nameInput.value.trim()) {
            const rawName = file.name.replace(/\.csv$/i, '').replace(/[_-]+/g, ' ');
            nameInput.value = rawName.charAt(0).toUpperCase() + rawName.slice(1);
        }

        submitBtn.disabled = false;
        previewBtn.style.display = 'inline-flex';
        fetchPreview();
    }

    previewBtn.addEventListener('click', fetchPreview);

    function fetchPreview() {
        if (!fileInput.files || fileInput.files.length === 0) return;
        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('delimiter', delimiterSelect.value);

        fetch("{{ route('datasets.preview') }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                renderPreviewTable(data.headers, data.preview_rows, data.delimiter);
            }
        })
        .catch(err => console.error("Preview error:", err));
    }

    function renderPreviewTable(headers, rows, delimiter) {
        const thead = document.getElementById('preview-thead');
        const tbody = document.getElementById('preview-tbody');
        const badge = document.getElementById('detected-delimiter-badge');

        badge.textContent = `Delimiter: "${delimiter}"`;

        let headerHtml = '<tr>';
        headers.forEach(h => {
            headerHtml += `<th>${escapeHtml(h)}</th>`;
        });
        headerHtml += '</tr>';
        thead.innerHTML = headerHtml;

        let bodyHtml = '';
        rows.forEach(r => {
            bodyHtml += '<tr>';
            r.forEach(cell => {
                bodyHtml += `<td>${escapeHtml(cell !== null ? String(cell) : '—')}</td>`;
            });
            bodyHtml += '</tr>';
        });
        tbody.innerHTML = bodyHtml;

        previewCard.style.display = 'block';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endpush

