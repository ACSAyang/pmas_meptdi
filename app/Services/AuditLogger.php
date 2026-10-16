<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Append-only audit trail (FR-011, BR-014).
 */
class AuditLogger
{
    public function log(
        string $action,
        ?Model $entity = null,
        ?array $before = null,
        ?array $after = null,
        ?string $description = null,
        ?string $reason = null,
        ?int $userId = null,
    ): AuditLog {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::query()->create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'description' => $description ? Str::limit($description, 495) : null,
            'before_data' => $this->clean($before),
            'after_data' => $this->clean($after),
            'reason' => $reason ? Str::limit($reason, 495) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250) : null,
            'created_at' => now(),
        ]);
    }

    protected function clean(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }
        unset($data['password'], $data['remember_token']);

        return array_map(function ($value) {
            if ($value instanceof \BackedEnum) {
                return $value->value;
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            return $value;
        }, $data);
    }
}
