<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.edit', ['values' => Settings::all(), 'labels' => Settings::LABELS]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'org_name' => ['required', 'string', 'max:200'],
            'org_short_name' => ['required', 'string', 'max:30'],
            'parent_org' => ['nullable', 'string', 'max:200'],
            'office_timezone' => ['required', 'timezone'],
            'overdue_grace_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'muster_validity_hours' => ['required', 'integer', 'min:1', 'max:168'],
            'upload_max_kb' => ['required', 'integer', 'min:100', 'max:20480'],
            'upload_types' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(,[a-z0-9]+)*$/'],
            'report_footer_note' => ['nullable', 'string', 'max:300'],
            'prepared_by_label' => ['required', 'string', 'max:50'],
            'verified_by_label' => ['required', 'string', 'max:50'],
            'noted_by_label' => ['required', 'string', 'max:50'],
        ]);
        $before = Settings::all();
        $changed = [];
        foreach ($data as $key => $value) {
            if ((string) ($before[$key] ?? '') !== (string) $value) {
                $changed[$key] = [$before[$key] ?? null, $value];
                Settings::set($key, (string) $value);
            }
        }
        if ($changed) {
            $audit->log('settings.updated', null, array_map(fn ($c) => $c[0], $changed), array_map(fn ($c) => $c[1], $changed), 'System settings changed');
        }

        return back()->with('success', $changed ? 'Settings saved.' : 'No changes.');
    }
}
