<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    use Auditable;

    public const ACTIONS = [
        'review' => 'Review / Recommend',
        'approve' => 'Final Approval',
    ];

    protected $fillable = ['approval_workflow_id', 'step_order', 'name', 'action_type', 'role_id', 'requires_documents_complete'];

    protected $casts = ['requires_documents_complete' => 'boolean'];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isFinal(): bool
    {
        return $this->action_type === 'approve';
    }

    public function nextStep(): ?ApprovalStep
    {
        return static::query()
            ->where('approval_workflow_id', $this->approval_workflow_id)
            ->where('step_order', '>', $this->step_order)
            ->orderBy('step_order')
            ->first();
    }
}
