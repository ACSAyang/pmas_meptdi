<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Personnel;
use App\Models\VerificationCase;
use App\Models\VerificationCaseAction;
use App\Services\VerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VerificationCaseController extends Controller
{
    public function __construct(protected VerificationService $service) {}

    public function index(Request $request): View
    {
        // Make sure newly overdue movements have cases even if the scheduler is not running.
        $this->service->openOverdueCases();
        $status = $request->input('status', 'open');

        return view('verification.index', [
            'cases' => VerificationCase::query()
                ->with(['personnel.rank', 'movement.application.type', 'movement.dutyAssignment', 'actions', 'resolver'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
                ->latest('opened_at')
                ->paginate(20)->withQueryString(),
            'status' => $status,
            'openCount' => VerificationCase::query()->where('status', 'open')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'personnel_id' => ['required', 'exists:personnel,id'],
            'reason' => ['required', Rule::in(array_keys(VerificationCase::REASONS))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $case = $this->service->open($request->user(), Personnel::query()->findOrFail($data['personnel_id']), $data['reason'], $data['notes'] ?? null);

        return redirect()->route('verification.show', $case)->with('success', 'Verification case #'.$case->id.' is open. Record each attempt to confirm the member\'s status.');
    }

    public function show(VerificationCase $case): View
    {
        $case->load(['personnel.rank', 'movement.application.type', 'movement.dutyAssignment', 'actions.user', 'opener', 'resolver']);

        return view('verification.show', [
            'case' => $case,
            'history' => AuditLog::query()->where('entity_type', 'VerificationCase')->where('entity_id', $case->id)->with('user')->latest('created_at')->get(),
        ]);
    }

    public function action(Request $request, VerificationCase $case): RedirectResponse
    {
        $data = $request->validate([
            'action_type' => ['required', Rule::in(array_keys(VerificationCaseAction::TYPES))],
            'details' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $this->service->addAction($request->user(), $case, $data['action_type'], $data['details']);

        return back()->with('success', 'Action recorded.');
    }

    public function resolve(Request $request, VerificationCase $case): RedirectResponse
    {
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['returned', 'extended', 'present', 'other'])],
            'actual_at' => ['nullable', 'date'],
            'new_return_at' => ['nullable', 'date'],
            'resolution' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $this->service->resolve($request->user(), $case, $data['outcome'], $data, $data['resolution']);

        return redirect()->route('verification.show', $case)->with('success', 'Case #'.$case->id.' resolved.');
    }
}
