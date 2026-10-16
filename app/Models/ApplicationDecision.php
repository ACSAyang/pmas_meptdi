<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDecision extends Model
{
    public const LABELS = [
        'created' => 'Draft created',
        'submitted' => 'Submitted',
        'resubmitted' => 'Resubmitted after correction',
        'recommended' => 'Recommended / Forwarded',
        'returned' => 'Returned for correction',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'closed' => 'Closed – movement completed',
    ];

    protected $fillable = [
        'application_id', 'approval_step_id', 'actor_user_id', 'decision',
        'from_status', 'to_status', 'remarks', 'decided_at',
    ];

    protected $casts = ['decided_at' => 'datetime'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(PersonnelApplication::class, 'application_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'approval_step_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->decision] ?? ucfirst($this->decision);
    }

    public function tone(): string
    {
        return match ($this->decision) {
            'approved', 'closed' => 'green',
            'rejected' => 'red',
            'returned' => 'orange',
            'recommended' => 'blue',
            'cancelled' => 'gray',
            default => 'navy',
        };
    }
}
