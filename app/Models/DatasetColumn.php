<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetColumn extends Model
{
    use HasFactory;

    protected $fillable = [
        'dataset_id',
        'field_key',
        'original_name',
        'display_name',
        'inferred_type',
        'semantic_role',
        'ordinal_position',
        'null_count',
        'distinct_count',
        'statistics',
        'sample_values',
    ];

    protected function casts(): array
    {
        return [
            'ordinal_position' => 'integer',
            'null_count' => 'integer',
            'distinct_count' => 'integer',
            'statistics' => 'array',
            'sample_values' => 'array',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }

    public function isNumeric(): bool
    {
        return in_array($this->inferred_type, ['integer', 'decimal'], true);
    }

    public function isDate(): bool
    {
        return in_array($this->inferred_type, ['date', 'datetime'], true);
    }
}

