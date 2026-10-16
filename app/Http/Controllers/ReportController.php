<?php

namespace App\Http\Controllers;

use App\Enums\AccountabilityCategory as Cat;
use App\Models\ApplicationType;
use App\Models\ReportRun;
use App\Models\Unit;
use App\Services\AccountabilityService;
use App\Services\AuditLogger;
use App\Services\ReportService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(protected AccountabilityService $accountability, protected ReportService $reports, protected AuditLogger $audit) {}

    public function index(): View
    {
        return view('reports.index', [
            'catalog' => ReportService::CATALOG,
            'recent' => ReportRun::query()->with('generator')->latest('generated_at')->limit(6)->get(),
        ]);
    }

    /** Daily Personnel Strength & Accountability Report (UC-07). */
    public function strength(Request $request)
    {
        [$asOf, $unit, $snapshot] = $this->strengthData($request);
        $canMed = $request->user()->hasPermission('medical.view');

        if ($request->input('format') === 'csv') {
            $this->audit->log('report.exported', null, null, ['type' => 'strength', 'as_of' => $asOf->format('Y-m-d H:i'), 'unit' => $unit?->code], 'Strength report exported');
            $rows = [];
            foreach ($snapshot->byUnit() as $u) {
                $rows[] = array_merge([$u['code'], $u['name'], $u['assigned']], array_values($u['counts']));
            }
            $rows[] = array_merge(['', 'TOTAL', $snapshot->assigned()], array_values($snapshot->counts()));

            return Csv::download('strength-report-'.$asOf->format('Ymd-Hi').'.csv',
                array_merge(['Unit code', 'Unit', 'Assigned'], array_map(fn ($c) => $c->label(), Cat::cases())), $rows);
        }

        return view('reports.strength', [
            'asOf' => $asOf,
            'unit' => $unit,
            'snapshot' => $snapshot,
            'units' => Unit::options(),
            'canMed' => $canMed,
            'live' => ! $request->filled('as_of'),
            'run' => null,
        ]);
    }

    public function snapshot(Request $request): RedirectResponse
    {
        [$asOf, $unit, $snapshot] = $this->strengthData($request);
        $canMed = $request->user()->hasPermission('medical.view');
        $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);

        $run = ReportRun::query()->create([
            'report_type' => 'strength',
            'title' => 'Daily Personnel Strength & Accountability'.($unit ? ' — '.$unit->name : ''),
            'as_of_at' => $asOf,
            'parameters' => ['unit_id' => $unit?->id, 'unit' => $unit?->name ?? 'All units', 'as_of' => $asOf->format('Y-m-d H:i')],
            'summary' => $snapshot->summaryArray() + ['by_unit' => $snapshot->byUnit()->map(fn ($u) => collect($u)->except('unit')->all())->all()],
            'payload' => json_encode($snapshot->rows->map(fn ($r) => $r->toArray(false))->values()),
            'generated_by' => $request->user()->id,
            'generated_at' => now(),
            'status' => 'generated',
            'remarks' => $request->input('remarks'),
        ]);
        $this->audit->log('report.snapshot_saved', $run, null, ['as_of' => $asOf->format('Y-m-d H:i'), 'assigned' => $snapshot->assigned()], 'Strength report snapshot saved');

        return redirect()->route('reports.snapshots.show', $run)->with('success', 'Snapshot saved. These figures are now preserved exactly as generated.');
    }

    public function snapshots(): View
    {
        return view('reports.snapshots', ['runs' => ReportRun::query()->with(['generator', 'certifier'])->latest('generated_at')->paginate(20)]);
    }

    public function showSnapshot(ReportRun $run): View
    {
        $run->load(['generator', 'certifier']);

        return view('reports.snapshot', ['run' => $run, 'rows' => collect($run->rows())]);
    }

    public function certify(Request $request, ReportRun $run): RedirectResponse
    {
        abort_if($run->status === 'certified', 422, 'Already certified.');
        abort_if($run->generated_by === $request->user()->id, 403, 'A report must be verified by someone other than the person who generated it.');
        $data = $request->validate(['remarks' => ['nullable', 'string', 'max:1000']]);
        $run->update(['status' => 'certified', 'certified_by' => $request->user()->id, 'certified_at' => now(), 'remarks' => trim(($run->remarks ? $run->remarks."\n" : '').($data['remarks'] ?? ''))]);
        $this->audit->log('report.certified', $run, ['status' => 'generated'], ['status' => 'certified'], 'Report snapshot verified');

        return back()->with('success', 'Report verified.');
    }

    public function show(Request $request, string $type)
    {
        if ($type === 'strength') {
            return redirect()->route('reports.strength', $request->query());
        }
        $data = $this->reports->build($type, $request);

        if ($request->input('format') === 'csv') {
            $this->audit->log('report.exported', null, null, ['type' => $type] + $request->only(['from', 'to', 'unit']), 'Report exported: '.$data['title']);

            return Csv::download($type.'-report-'.now()->format('Ymd-His').'.csv', $data['columns'], $data['rows']);
        }

        return view('reports.generic', $data + [
            'type' => $type,
            'units' => Unit::options(),
            'types' => ApplicationType::query()->orderBy('sort_order')->get(),
            'icon' => ReportService::CATALOG[$type][2],
            'description' => ReportService::CATALOG[$type][1],
        ]);
    }

    protected function strengthData(Request $request): array
    {
        $asOf = $request->filled('as_of') ? Carbon::parse($request->input('as_of')) : now();
        if ($asOf->isFuture()) {
            $asOf = now();
        }
        $unit = $request->filled('unit') ? Unit::query()->find($request->integer('unit')) : null;

        return [$asOf, $unit, $this->accountability->snapshot($asOf, $unit?->descendantIds())];
    }
}
