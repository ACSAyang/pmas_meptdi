<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'entity_type', 'entity_id', 'description', 'before_data',
        'after_data', 'reason', 'ip_address', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actionTone(): string
    {
        return match (true) {
            str_contains($this->action, 'approved'), str_contains($this->action, 'returned_home') => 'green',
            str_contains($this->action, 'rejected'), str_contains($this->action, 'failed'), str_contains($this->action, 'suspend') => 'red',
            str_contains($this->action, 'correct'), str_contains($this->action, 'void') => 'orange',
            str_contains($this->action, 'login') || str_contains($this->action, 'logout') => 'gray',
            default => 'navy',
        };
    }
}
