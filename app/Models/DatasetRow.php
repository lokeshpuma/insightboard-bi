<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatasetRow extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'dataset_id',
        'row_number',
        'row_data',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'row_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function dataset(): BelongsTo
    {
        return $this->belongsTo(Dataset::class);
    }
}

