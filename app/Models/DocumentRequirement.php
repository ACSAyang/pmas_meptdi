<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequirement extends Model
{
    use Auditable;

    protected $fillable = [
        'application_type_id', 'name', 'description', 'is_required', 'is_sensitive',
        'effective_from', 'effective_to', 'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_sensitive' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function applicationType(): BelongsTo
    {
        return $this->belongsTo(ApplicationType::class);
    }
}
