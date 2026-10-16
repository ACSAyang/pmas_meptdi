<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

/**
 * Records create/update events of master and configuration data in the audit
 * trail with before/after values (BR-014). Domain actions such as approvals and
 * movements are logged explicitly by their services with a business action name.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(AuditLogger::class)->log(
                static::auditPrefix().'.created',
                $model,
                null,
                $model->auditableAttributes($model->getAttributes()),
            );
        });

        static::updated(function ($model) {
            $changes = $model->auditableAttributes($model->getChanges());
            unset($changes['updated_at']);
            if ($changes === []) {
                return;
            }
            $before = array_intersect_key($model->auditableAttributes($model->getOriginal()), $changes);
            app(AuditLogger::class)->log(static::auditPrefix().'.updated', $model, $before, $changes);
        });
    }

    public static function auditPrefix(): string
    {
        return \Illuminate\Support\Str::snake(class_basename(static::class));
    }

    protected function auditableAttributes(array $attributes): array
    {
        $hidden = array_merge(['password', 'remember_token'], $this->auditExclude ?? []);

        return array_diff_key($attributes, array_flip($hidden));
    }
}
