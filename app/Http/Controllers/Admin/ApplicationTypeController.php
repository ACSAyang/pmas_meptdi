<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountabilityCategory;
use App\Http\Controllers\Controller;
use App\Models\ApplicationType;
use App\Models\ApprovalWorkflow;
use App\Models\DocumentRequirement;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Policy configuration per application type: notice period, limits, checklist
 * (versioned by effective dates) and approval workflow (versioned).
 */
class ApplicationTypeController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.types.index', [
            'types' => ApplicationType::query()->withCount('documentRequirements')->with(['workflows' => fn ($q) => $q->where('is_active', true)->with('steps.role')])->orderBy('sort_order')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.types.edit', ['type' => new ApplicationType(['is_active' => true, 'requires_movement' => true, 'requires_destination' => true, 'allow_on_behalf' => true, 'color' => 'navy']), 'roles' => Role::query()->orderBy('name')->get(), 'workflow' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = ApplicationType::query()->create($this->validated($request));
        $reviewer = Role::query()->where('slug', Role::REVIEWER)->value('id');
        $approver = Role::query()->where('slug', Role::APPROVER)->value('id');
        $wf = ApprovalWorkflow::query()->create(['application_type_id' => $type->id, 'version' => 1, 'name' => $type->name.' – routing v1', 'is_active' => true, 'active_from' => now()]);
        $wf->steps()->create(['step_order' => 1, 'name' => 'Immediate Supervisor Review', 'action_type' => 'review', 'role_id' => $reviewer]);
        $wf->steps()->create(['step_order' => 2, 'name' => 'Approving Authority', 'action_type' => 'approve', 'role_id' => $approver, 'requires_documents_complete' => true]);

        return redirect()->route('admin.types.edit', $type)->with('success', 'Application type created with a default two-stage workflow. Adjust it below.');
    }

    public function edit(ApplicationType $type): View
    {
        return view('admin.types.edit', [
            'type' => $type->load('documentRequirements'),
            'roles' => Role::query()->orderBy('name')->get(),
            'workflow' => $type->activeWorkflow(),
            'versions' => $type->workflows()->withCount('steps')->get(),
        ]);
    }

    public function update(Request $request, ApplicationType $type): RedirectResponse
    {
        $type->update($this->validated($request, $type));

        return back()->with('success', 'Policy settings saved. They apply to new submissions.');
    }

    public function addRequirement(Request $request, ApplicationType $type): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
        ]);
        $type->documentRequirements()->create($data + [
            'is_required' => $request->boolean('is_required'),
            'is_sensitive' => $request->boolean('is_sensitive'),
            'sort_order' => (int) $type->documentRequirements()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Requirement added to the checklist.');
    }

    /** Requirements are never deleted: they are retired with an end date so history stays intact. */
    public function retireRequirement(Request $request, DocumentRequirement $requirement): RedirectResponse
    {
        $requirement->update(['effective_to' => now()->subDay()->toDateString()]);

        return back()->with('success', 'Requirement retired. Earlier applications keep their original checklist.');
    }

    /** Saves a NEW workflow version; pending applications continue on their original version. */
    public function saveWorkflow(Request $request, ApplicationType $type): RedirectResponse
    {
        $data = $request->validate([
            'steps' => ['required', 'array', 'min:1', 'max:6'],
            'steps.*.name' => ['required', 'string', 'max:100'],
            'steps.*.action_type' => ['required', Rule::in(['review', 'approve'])],
            'steps.*.role_id' => ['required', 'exists:roles,id'],
            'steps.*.requires_documents_complete' => ['nullable', 'boolean'],
        ]);
        $steps = array_values($data['steps']);
        $finals = collect($steps)->where('action_type', 'approve');
        if ($finals->count() !== 1 || end($steps)['action_type'] !== 'approve') {
            return back()->withErrors(['steps' => 'The workflow must end with exactly one "Final approval" stage.']);
        }

        DB::transaction(function () use ($type, $steps) {
            $current = $type->workflows()->where('is_active', true)->first();
            $current?->update(['is_active' => false, 'active_to' => now()]);
            $version = (int) $type->workflows()->max('version') + 1;
            $wf = ApprovalWorkflow::query()->create(['application_type_id' => $type->id, 'version' => $version, 'name' => $type->name.' – routing v'.$version, 'is_active' => true, 'active_from' => now()]);
            foreach ($steps as $i => $s) {
                $wf->steps()->create(['step_order' => $i + 1, 'name' => $s['name'], 'action_type' => $s['action_type'], 'role_id' => $s['role_id'], 'requires_documents_complete' => (bool) ($s['requires_documents_complete'] ?? false)]);
            }
        });

        return back()->with('success', 'New workflow version saved. Applications already in progress keep the version they were submitted under.');
    }

    protected function validated(Request $request, ?ApplicationType $type = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('application_types', 'code')->ignore($type?->id)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', Rule::in(array_map(fn ($c) => $c->value, AccountabilityCategory::awayCategories()))],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        foreach (['requires_destination', 'requires_movement', 'is_sensitive', 'allow_on_behalf', 'is_active'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }
        $data['code'] = strtoupper($data['code']);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }
}
