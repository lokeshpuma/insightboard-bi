<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'actor_user_id',
        'action',
        'resource_type',
        'resource_id',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public static function record(
        Workspace|int $workspace,
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        ?array $metadata = null,
        ?User $actor = null,
        ?string $ipAddress = null
    ): self {
        $workspaceId = $workspace instanceof Workspace ? $workspace->id : $workspace;
        $actorId = $actor ? $actor->id : auth()->id();

        return static::create([
            'workspace_id' => $workspaceId,
            'actor_user_id' => $actorId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'metadata' => $metadata,
            'ip_address' => $ipAddress ?? request()->ip(),
            'created_at' => now(),
        ]);
    }
}

