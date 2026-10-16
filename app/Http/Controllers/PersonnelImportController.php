<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\Rank;
use App\Models\Unit;
use App\Services\AuditLogger;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Masterlist import (UC-06, FR-014): upload CSV -> validated preview -> confirm.
 * Existing service numbers are reported, never silently overwritten.
 */
class PersonnelImportController extends Controller
{
    public const COLUMNS = ['service_number', 'rank', 'last_name', 'first_name', 'middle_name', 'suffix', 'sex', 'unit_code', 'assignment_type', 'start_date', 'designation', 'email', 'contact_number'];

    public function create(): View
    {
        return view('personnel.import', ['units' => Unit::options(), 'ranks' => Rank::query()->orderBy('sort_order')->get()]);
    }

    public function template(): StreamedResponse
    {
        return Csv::download('pmas-masterlist-template.csv', self::COLUMNS, [
            ['SN-0001', 'PO2', 'Dela Cruz', 'Juan', 'Santos', '', 'M', 'ADMIN', 'regular', now()->toDateString(), 'Admin Clerk', '', ''],
        ]);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle, 0, ',', '"', '\\');
        if (! $header) {
            return back()->withErrors(['file' => 'The file is empty.']);
        }
        $header = array_map(fn ($h) => Str::snake(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $missing = array_diff(['service_number', 'rank', 'last_name', 'first_name', 'unit_code'], $header);
        if ($missing) {
            return back()->withErrors(['file' => 'Missing required columns: '.implode(', ', $missing).'. Download the template for the expected format.']);
        }

        $ranks = Rank::query()->get()->keyBy(fn ($r) => strtoupper($r->abbreviation));
        $units = Unit::query()->get()->keyBy(fn ($u) => strtoupper($u->code));
        $existing = Personnel::query()->pluck('service_number')->map(fn ($s) => strtoupper($s))->flip();
        $seen = [];
        $rows = [];
        $line = 1;
        while (($data = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            $line++;
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $r = array_combine($header, array_pad(array_slice($data, 0, count($header)), count($header), null));
            $r = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $r);
            $errors = [];
            $sn = strtoupper($r['service_number'] ?? '');
            if ($sn === '') {
                $errors[] = 'Service number is required';
            } elseif (isset($existing[$sn])) {
                $errors[] = 'Service number already exists';
            } elseif (isset($seen[$sn])) {
                $errors[] = 'Duplicate service number in file (line '.$seen[$sn].')';
            }
            $seen[$sn] = $line;
            if (! isset($ranks[strtoupper($r['rank'] ?? '')])) {
                $errors[] = 'Unknown rank "'.($r['rank'] ?? '').'"';
            }
            if (! isset($units[strtoupper($r['unit_code'] ?? '')])) {
                $errors[] = 'Unknown unit code "'.($r['unit_code'] ?? '').'"';
            }
            if (blank($r['last_name'] ?? null) || blank($r['first_name'] ?? null)) {
                $errors[] = 'Name is incomplete';
            }
            $type = strtolower($r['assignment_type'] ?? '') ?: 'regular';
            if (! array_key_exists($type, PersonnelAssignment::TYPES)) {
                $errors[] = 'Invalid assignment type';
            }
            $start = $r['start_date'] ?? null;
            if ($start && strtotime($start) === false) {
                $errors[] = 'Invalid start date';
            }
            if (! empty($r['sex']) && ! in_array(strtoupper($r['sex']), ['M', 'F'], true)) {
                $errors[] = 'Sex must be M or F';
            }
            if (! empty($r['email']) && ! filter_var($r['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid e-mail';
            }
            $rows[] = ['line' => $line, 'data' => $r + ['assignment_type' => $type], 'errors' => $errors];
            if (count($rows) > 2000) {
                break;
            }
        }
        fclose($handle);

        $token = Str::random(32);
        $request->session()->put('import.'.$token, collect($rows)->filter(fn ($r) => ! $r['errors'])->values()->all());

        return view('personnel.import-preview', [
            'rows' => $rows,
            'token' => $token,
            'valid' => collect($rows)->filter(fn ($r) => ! $r['errors'])->count(),
            'invalid' => collect($rows)->filter(fn ($r) => (bool) $r['errors'])->count(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $token = $request->validate(['token' => ['required', 'string', 'size:32']])['token'];
        $rows = $request->session()->pull('import.'.$token);
        if (! $rows) {
            return redirect()->route('personnel.import')->withErrors(['file' => 'The import session expired. Upload the file again.']);
        }

        $ranks = Rank::query()->get()->keyBy(fn ($r) => strtoupper($r->abbreviation));
        $units = Unit::query()->get()->keyBy(fn ($u) => strtoupper($u->code));
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $ranks, $units, $request, &$created, &$skipped) {
            foreach ($rows as $row) {
                $r = $row['data'];
                if (Personnel::query()->where('service_number', $r['service_number'])->exists()) {
                    $skipped++;

                    continue;
                }
                $p = Personnel::query()->create([
                    'service_number' => $r['service_number'],
                    'rank_id' => $ranks[strtoupper($r['rank'])]->id,
                    'last_name' => $r['last_name'],
                    'first_name' => $r['first_name'],
                    'middle_name' => $r['middle_name'] ?? null ?: null,
                    'suffix' => $r['suffix'] ?? null ?: null,
                    'sex' => ! empty($r['sex']) ? strtoupper($r['sex']) : null,
                    'designation' => $r['designation'] ?? null ?: null,
                    'email' => $r['email'] ?? null ?: null,
                    'contact_number' => $r['contact_number'] ?? null ?: null,
                    'service_status' => 'active',
                ]);
                PersonnelAssignment::query()->create([
                    'personnel_id' => $p->id,
                    'unit_id' => $units[strtoupper($r['unit_code'])]->id,
                    'assignment_type' => $r['assignment_type'],
                    'start_date' => ! empty($r['start_date']) ? date('Y-m-d', strtotime($r['start_date'])) : now()->toDateString(),
                    'order_reference' => 'Masterlist import',
                    'created_by' => $request->user()->id,
                ]);
                $created++;
            }
        });
        $audit->log('personnel.imported', null, null, ['created' => $created, 'skipped' => $skipped], 'Masterlist CSV import');

        return redirect()->route('personnel.index')->with('success', "Import complete: {$created} member(s) added".($skipped ? ", {$skipped} skipped (already exist)." : '.'));
    }
}
