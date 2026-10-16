<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Policy-driven configuration stored in the `settings` table.
 * Defaults below are placeholders until MEPTDI confirms official values.
 */
class Settings
{
    public const DEFAULTS = [
        'org_name' => 'Marine Environmental Protection Training and Development Institute',
        'org_short_name' => 'MEPTDI',
        'parent_org' => 'Philippine Coast Guard',
        'office_timezone' => 'Asia/Manila',
        'overdue_grace_minutes' => '0',
        'muster_validity_hours' => '24',
        'upload_max_kb' => '5120',
        'upload_types' => 'pdf,jpg,jpeg,png',
        'report_footer_note' => 'System-generated report. Not an official document until reviewed and signed by the authorized officer.',
        'prepared_by_label' => 'Prepared by',
        'verified_by_label' => 'Verified by',
        'noted_by_label' => 'Noted by',
    ];

    public const LABELS = [
        'org_name' => ['Organization name', 'Displayed on reports and the login page.'],
        'org_short_name' => ['Short name', 'Displayed in the sidebar and page titles.'],
        'parent_org' => ['Parent organization', 'Optional line shown above the organization name on printed reports.'],
        'office_timezone' => ['Office timezone', 'Reporting timezone displayed on reports (application timezone is set in .env APP_TIMEZONE).'],
        'overdue_grace_minutes' => ['Overdue grace period (minutes)', 'Minutes after the expected return before an open movement is flagged overdue (BR-009).'],
        'muster_validity_hours' => ['Muster validity (hours)', 'How long a "present" confirmation remains valid for accountability before the member becomes unverified.'],
        'upload_max_kb' => ['Maximum upload size (KB)', 'Per supporting-document file.'],
        'upload_types' => ['Allowed upload types', 'Comma-separated file extensions.'],
        'report_footer_note' => ['Report footer note', 'Printed at the bottom of every generated report.'],
        'prepared_by_label' => ['Signature block 1 label', 'e.g. Prepared by'],
        'verified_by_label' => ['Signature block 2 label', 'e.g. Verified by'],
        'noted_by_label' => ['Signature block 3 label', 'e.g. Noted by'],
    ];

    protected static ?array $cache = null;

    public static function all(): array
    {
        if (static::$cache === null) {
            try {
                $stored = Cache::remember('pmas.settings', 300, function () {
                    return Schema::hasTable('settings') ? Setting::query()->pluck('value', 'key')->all() : [];
                });
            } catch (Throwable) {
                $stored = [];
            }
            static::$cache = array_merge(self::DEFAULTS, $stored);
        }

        return static::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::all()[$key] ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = static::get($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        static::flush();
    }

    public static function flush(): void
    {
        static::$cache = null;
        Cache::forget('pmas.settings');
    }
}
