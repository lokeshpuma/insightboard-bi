<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dataset extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'workspace_id',
        'created_by',
        'name',
        'original_filename',
        'storage_path',
        'file_size_bytes',
        'file_checksum_sha256',
        'delimiter',
        'encoding',
        'row_count',
        'column_count',
        'status',
        'analysis_summary',
        'last_profiled_at',
    ];

    protected function casts(): array
    {
        return [
            'analysis_summary' => 'array',
            'last_profiled_at' => 'datetime',
            'file_size_bytes' => 'integer',
            'row_count' => 'integer',
            'column_count' => 'integer',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(DatasetColumn::class)->orderBy('ordinal_position');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DatasetRow::class)->orderBy('row_number');
    }

    public function importJobs(): HasMany
    {
        return $this->hasMany(ImportJob::class)->orderByDesc('created_at');
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(DashboardWidget::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }
}

