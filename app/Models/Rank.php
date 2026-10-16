<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rank extends Model
{
    use Auditable;

    protected $fillable = ['name', 'abbreviation', 'category', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function personnel(): HasMany
    {
        return $this->hasMany(Personnel::class);
    }
}
