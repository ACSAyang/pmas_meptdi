<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use Auditable;

    protected $fillable = ['parent_id', 'code', 'name', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Unit::class, 'parent_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(PersonnelAssignment::class);
    }

    /** IDs of this unit and all descendants (for "include sub-units" filters). */
    public function descendantIds(): array
    {
        $all = static::query()->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [$this->id];
        $stack = [$this->id];
        while ($stack) {
            $current = array_pop($stack);
            foreach ($all->get($current, collect()) as $child) {
                $ids[] = $child->id;
                $stack[] = $child->id;
            }
        }

        return $ids;
    }

    public static function options()
    {
        return static::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    }
}
