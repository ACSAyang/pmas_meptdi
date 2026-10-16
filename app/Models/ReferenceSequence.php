<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReferenceSequence extends Model
{
    protected $fillable = ['prefix', 'year', 'last_number'];

    /**
     * Concurrency-safe next number. Must be called inside a DB transaction.
     */
    public static function next(string $prefix, int $year): int
    {
        return DB::transaction(function () use ($prefix, $year) {
            $seq = static::query()->where('prefix', $prefix)->where('year', $year)->lockForUpdate()->first();
            if (! $seq) {
                $seq = static::query()->create(['prefix' => $prefix, 'year' => $year, 'last_number' => 0]);
                $seq = static::query()->whereKey($seq->id)->lockForUpdate()->first();
            }
            $seq->increment('last_number');

            return $seq->last_number;
        });
    }
}
