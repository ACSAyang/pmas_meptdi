<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use Auditable;

    public const ADMIN = 'admin';
    public const DUTY_WATCH = 'duty_watch';
    public const PERSONNEL = 'personnel';
    public const REVIEWER = 'reviewer';
    public const APPROVER = 'approver';
    public const RECORDS = 'records';
    public const COMMAND = 'command';

    protected $fillable = ['slug', 'name', 'description', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
