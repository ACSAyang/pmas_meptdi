<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Http\Requests\ApplicationRequest;
use App\Models\ApplicationType;
use App\Models\Personnel;
use App\Models\PersonnelApplication;
use App\Services\ApplicationWorkflowService;
use App\Support\Csv;
use App\Support\NavCounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function __construct(protected ApplicationWorkflowService $workflow) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $canAll = $user->hasPermission('applications.view_all');
        $scope = $canAll ? $request->input('scope', 'all') : 'mine';

        $query = PersonnelApplication::query()
            ->with(['personnel.rank', 'type', 'currentStep'])
            ->when($scope === 'mine', fn ($q) => $q->where(fn ($q) => $q->where('personnel_id', $user->personnel_id ?? 0)->orWhere('created_by', $user->id)))
            ->when($scope === 'all' && ! $user->hasPermission('medical.view'), fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('type', fn ($t) => $t->where('is_sensitive', false))
                ->orWhere('personnel_id', $user->personnel_id ?? 0)))
            ->when($scope === 'all', fn ($q) => $q->where(fn ($q) => $q->where('status', '!=', ApplicationStatus::Draft->value)->orWhere('created_by', $user->id)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('application_type_id', $request->integer('type')))
            ->when($request->filled('from'), fn ($q) => $q->where('end_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('start_at', '<=', $request->date('to')->endOfDay()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.trim($request->input('q')).'%';
                $q->where(fn ($q) => $q->where('reference_number', 'like', $term)
                    ->orWhereHas('personnel', fn ($p) => $p->where('last_name', 'like', $term)->orWhere('first_name', 'like', $term)->orWhere('service_number', 'like', $term)));
            })
            ->latest('updated_at');

        if ($request->input('format') === 'csv' && $canAll) {
            $rows = $query->get();

            return Csv::download('applications-'.now()->format('Ymd-His').'.csv',
                ['Reference', 'Service No.', 'Rank', 'Name', 'Type', 'Departure', 'Return', 'Status', 'Current stage', 'Submitted', 'Late'],
                $rows->map(fn ($a) => [$a->reference_number, $a->personnel->service_number, $a->personnel->rank?->abbreviation, $a->personnel->full_name,
                    $a->type->name, $a->start_at->format('Y-m-d H:i'), $a->end_at->format('Y-m-d H:i'), $a->status->label(), $a->currentStep?->name,
                    $a->submitted_at?->format('Y-m-d H:i'), $a->is_late || $a->is_retrospective ? 'Yes' : 'No']));
        }

        return view('applications.index', [
            'applications' => $query->paginate(20)->withQueryString(),
            'scope' => $scope,
            'canAll' => $canAll,
            'types' => ApplicationType::query()->orderBy('sort_order')->get(),
            'filters' => $request->only(['status', 'type', 'q', 'from', 'to']),
        ]);
    }

    public function queue(Request $request): View
    {
        $user = $request->user();

        return view('applications.queue', [
            'applications' => NavCounts::approvalQueue($user)->with(['personnel.rank', 'type', 'currentStep', 'documents'])
                ->orderBy('start_at')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->hasPermission('applications.create') || $user->hasPermission('applications.create_on_behalf'), 403);

        return view('applications.form', $this->formData($request, new PersonnelApplication([
            'personnel_id' => $request->integer('personnel') ?: $user->personnel_id,
            'start_at' => now()->addDay()->setTime(8, 0),
            'end_at' => now()->addDay()->setTime(17, 0),
        ])));
    }

    public function store(ApplicationRequest $request): RedirectResponse
    {
        $user = $request->user();
        $personnel = $this->resolvePersonnel($request);
        $type = ApplicationType::query()->findOrFail($request->integer('application_type_id'));
        if ($personnel->id !== $user->personnel_id && ! $type->allow_on_behalf) {
            abort(403, 'This application type cannot be prepared on behalf of another member.');
        }

        $app = $this->workflow->createDraft($user, $personnel, $type, $request->validated());

        if ($request->input('intent') === 'submit') {
            $this->workflow->submit($user, $app);

            return redirect()->route('applications.show', $app)->with('success', 'Application '.$app->fresh()->reference_number.' submitted for review. Upload any remaining documents below.');
        }

        return redirect()->route('applications.show', $app)->with('success', 'Draft saved. Attach the supporting documents, then submit.');
    }

    public function show(Request $request, PersonnelApplication $application): View
    {
        Gate::authorize('view', $application);
        $application->load(['personnel.rank', 'personnel.assignments.unit', 'type', 'workflow.steps.role', 'currentStep.role',
            'decisions.actor', 'decisions.step', 'documents.uploader', 'documents.reviewer', 'documents.requirement', 'movement.departureRecorder', 'movement.returnRecorder', 'creator', 'canceller']);
        $user = $request->user();

        return view('applications.show', [
            'a' => $application,
            'canSeeDetails' => Gate::allows('viewSensitiveDetails', $application),
            'canAct' => Gate::allows('act', $application),
            'checklist' => $application->requirementChecklist(),
            'workflowSteps' => $application->workflow?->steps ?? $application->type->activeWorkflow()?->steps ?? collect(),
            'actBlocker' => $this->actBlocker($user, $application),
        ]);
    }

    /** Explains why the current user cannot act (shown to step-role holders). */
    protected function actBlocker($user, PersonnelApplication $app): ?string
    {
        if ($app->status !== ApplicationStatus::Pending || ! $app->currentStep || ! $user->roles->contains('id', $app->currentStep->role_id)) {
            return null;
        }
        if (Gate::allows('act', $app)) {
            return null;
        }
        if ($user->personnel_id === $app->personnel_id) {
            return 'You cannot act on your own application.';
        }

        return 'You already acted on an earlier stage of this application; another authorized officer must decide this stage.';
    }

    public function edit(Request $request, PersonnelApplication $application): View
    {
        Gate::authorize('update', $application);

        return view('applications.form', $this->formData($request, $application));
    }

    public function update(ApplicationRequest $request, PersonnelApplication $application): RedirectResponse
    {
        Gate::authorize('update', $application);
        $this->workflow->updateDraft($request->user(), $application, $request->validated());

        if ($request->input('intent') === 'submit') {
            $this->workflow->submit($request->user(), $application);

            return redirect()->route('applications.show', $application)->with('success', 'Application '.$application->fresh()->reference_number.' submitted.');
        }

        return redirect()->route('applications.show', $application)->with('success', 'Changes saved.');
    }

    public function submit(Request $request, PersonnelApplication $application): RedirectResponse
    {
        Gate::authorize('submit', $application);
        $this->workflow->submit($request->user(), $application);

        return redirect()->route('applications.show', $application)->with('success', 'Application '.$application->fresh()->reference_number.' submitted and routed to '.$application->fresh()->currentStep?->name.'.');
    }

    public function decide(Request $request, PersonnelApplication $application): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['recommend', 'approve', 'return', 'reject'])],
            'step_id' => ['required', 'integer'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        Gate::authorize('view', $application);
        $this->workflow->decide($request->user(), $application, $data['decision'], (int) $data['step_id'], $data['remarks'] ?? null);
        $fresh = $application->fresh('currentStep');

        $message = match ($fresh->status) {
            ApplicationStatus::Approved => 'Application approved. A scheduled movement was created — the Duty Office Watch records the actual departure.',
            ApplicationStatus::Rejected => 'Application rejected. The applicant has been notified.',
            ApplicationStatus::Returned => 'Application returned to the applicant for correction.',
            default => 'Recommendation recorded. Forwarded to '.$fresh->currentStep?->name.'.',
        };

        return redirect()->route($request->user()->hasPermission('applications.act') ? 'approvals.index' : 'applications.show', $request->user()->hasPermission('applications.act') ? [] : $application)
            ->with('success', $application->reference_number.': '.$message);
    }

    public function cancel(Request $request, PersonnelApplication $application): RedirectResponse
    {
        Gate::authorize('cancel', $application);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->workflow->cancel($request->user(), $application, $data['reason']);

        return redirect()->route('applications.show', $application)->with('success', 'Application cancelled. The history has been preserved.');
    }

    public function print(Request $request, PersonnelApplication $application): View
    {
        Gate::authorize('view', $application);
        $application->load(['personnel.rank', 'personnel.assignments.unit', 'type', 'decisions.actor', 'decisions.step', 'movement']);

        return view('applications.print', [
            'a' => $application,
            'canSeeDetails' => Gate::allows('viewSensitiveDetails', $application),
        ]);
    }

    // ---------------------------------------------------------------- helpers

    protected function resolvePersonnel(Request $request): Personnel
    {
        $user = $request->user();
        $id = $request->integer('personnel_id') ?: $user->personnel_id;
        abort_unless($id, 422, 'Your account is not linked to a personnel record. Select the member.');

        if ($id !== $user->personnel_id) {
            abort_unless($user->hasPermission('applications.create_on_behalf'), 403, 'You may only submit applications for yourself.');
        } else {
            abort_unless($user->hasPermission('applications.create'), 403);
        }

        return Personnel::query()->findOrFail($id);
    }

    protected function formData(Request $request, PersonnelApplication $app): array
    {
        $user = $request->user();
        $types = ApplicationType::query()->where('is_active', true)->with(['documentRequirements' => fn ($q) => $q->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', now()->toDateString()))])->orderBy('sort_order')->get();

        return [
            'a' => $app,
            'types' => $types,
            'onBehalf' => $user->hasPermission('applications.create_on_behalf'),
            'personnelOptions' => $user->hasPermission('applications.create_on_behalf')
                ? Personnel::query()->assignedOn(now())->where('service_status', 'active')->with('rank')->ordered()->get()
                : collect(),
            'self' => $user->personnel,
        ];
    }
}
