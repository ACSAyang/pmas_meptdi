<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    protected $fillable = [
        'application_id', 'document_requirement_id', 'original_name', 'storage_path', 'mime_type',
        'size_bytes', 'checksum', 'uploaded_by', 'review_status', 'reviewed_by', 'reviewed_at',
        'review_remarks', 'is_superseded',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'is_superseded' => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(PersonnelApplication::class, 'application_id');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(DocumentRequirement::class, 'document_requirement_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isSensitive(): bool
    {
        return (bool) ($this->requirement?->is_sensitive || $this->application?->type?->is_sensitive);
    }

    public function humanSize(): string
    {
        $bytes = $this->size_bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }
}
