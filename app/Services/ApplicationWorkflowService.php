<?php

namespace App\Services;

use App\Enums\ApplicationStatus as Status;
use App\Models\ApplicationDecision;
use App\Models\ApplicationDocument;
use App\Models\ApplicationType;
use App\Models\ApprovalStep;
use App\Models\Personnel;
use App\Models\PersonnelApplication;
use App\Models\ReferenceSequence;
use App\Models\User;
use App\Notifications\ApplicationActionRequired;
use App\Notifications\ApplicationStatusChanged;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Application lifecycle: draft -> submitted (pending at step N) -> recommended ...
 * -> approved -> (movement) -> closed, with return/reject/cancel branches.
 *
 * All transitions run in transactions with row locks to prevent concurrent
 * duplicate decisions, and every transition writes a decision record and an
 * audit entry.
 */
class ApplicationWorkflowService
{
    public function __construct(
        protected AuditLogger $audit,
        protected MovementService $movements,
    ) {}

    public function createDraft(User $actor, Personnel $personnel, ApplicationType $type, array $data): PersonnelApplication
    {
        $this->validateBusinessRules($personnel, $type, $data);

        return DB::transaction(function () use ($actor, $personnel, $type, $data) {
            $app = PersonnelApplication::query()->create([
                'personnel_id' => $personnel->id,
                'application_type_id' => $type->id,
                'created_by' => $actor->id,
                'status' => Status::Draft,
                'purpose' => $data['purpose'],
                'destination' => $data['destination'] ?? null,
                'contact_while_away' => $data['contact_while_away'] ?? null,
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'],
                'late_reason' => $data['late_reason'] ?? null,
            ]);
            $this->recordDecision($app, $actor, 'created', null, Status::Draft, $personnel->id === $actor->personnel_id ? null : 'Prepared on behalf of the member');
            $this->audit->log('application.created', $app, null, ['type' => $type->code, 'personnel_id' => $personnel->id], 'Draft application created');

            return $app;
        });
    }

    public function updateDraft(User $actor, PersonnelApplication $app, array $data): PersonnelApplication
    {
        if (! $app->status->isEditable()) {
            throw ValidationException::withMessages(['status' => 'Only drafts or returned applications can be edited.']);
        }
        $type = ApplicationType::query()->findOrFail($data['application_type_id'] ?? $app->application_type_id);
        $this->validateBusinessRules($app->personnel, $type, $data, $app->id);

        $before = $app->only(['application_type_id', 'purpose', 'destination', 'start_at', 'end_at']);
        $app->update([
            'application_type_id' => $type->id,
            'purpose' => $data['purpose'],
            'destination' => $data['destination'] ?? null,
            'contact_while_away' => $data['contact_while_away'] ?? null,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'],
            'late_reason' => $data['late_reason'] ?? null,
        ]);
        $this->audit->log('application.updated', $app, $before, $app->only(array_keys($before)), 'Application details edited');

        return $app;
    }

    /**
     * Submit (or resubmit after correction). Assigns a reference number,
     * snapshots the active workflow and routes to the first step (UC-01).
     */
    public function submit(User $actor, PersonnelApplication $app): PersonnelApplication
    {
        return DB::transaction(function () use ($actor, $app) {
            /** @var PersonnelApplication $app */
            $app = PersonnelApplication::query()->whereKey($app->id)->lockForUpdate()->firstOrFail();
            if (! $app->status->isEditable()) {
                throw ValidationException::withMessages(['status' => 'This application has already been submitted.']);
            }
            $type = $app->type;
            $this->validateBusinessRules($app->personnel, $type, $app->only(['start_at', 'end_at', 'destination', 'late_reason']), $app->id);

            $workflow = $app->workflow ?? $type->activeWorkflow();
            if (! $workflow || $workflow->steps->isEmpty()) {
                throw ValidationException::withMessages(['workflow' => 'No approval workflow is configured for '.$type->name.'. Contact the system administrator.']);
            }

            $wasReturned = $app->status === Status::Returned;
            $now = now();
            $timing = $this->timingFlags($type, Carbon::parse($app->start_at), $now);

            $app->fill([
                'approval_workflow_id' => $workflow->id,
                'current_step_id' => $workflow->steps->first()->id,
                'status' => Status::Pending,
                'submitted_at' => $app->submitted_at ?? $now,
                'is_late' => $timing['late'],
                'is_retrospective' => $timing['retrospective'],
            ]);
            if (! $app->reference_number) {
                $year = (int) $now->format('Y');
                $number = ReferenceSequence::next($type->code, $year);
                $app->reference_number = sprintf('%s-%d-%05d', $type->code, $year, $number);
            }
            $app->save();

            $this->recordDecision($app, $actor, $wasReturned ? 'resubmitted' : 'submitted', $wasReturned ? Status::Returned : Status::Draft, Status::Pending);
            $this->audit->log('application.submitted', $app, null, [
                'reference_number' => $app->reference_number,
                'step' => $workflow->steps->first()->name,
                'is_late' => $app->is_late,
            ], 'Application submitted for review');

            DB::afterCommit(fn () => $this->notifyStepActors($app->fresh(['currentStep.role', 'personnel.rank', 'type'])));

            return $app;
        });
    }

    /**
     * Decision on the current step: recommend (review step), approve (final step),
     * return for correction or reject (UC-02, UC-03).
     */
    public function decide(User $actor, PersonnelApplication $app, string $decision, int $stepId, ?string $remarks): PersonnelApplication
    {
        $allowed = ['recommend', 'approve', 'return', 'reject'];
        if (! in_array($decision, $allowed, true)) {
            throw ValidationException::withMessages(['decision' => 'Invalid decision.']);
        }

        return DB::transaction(function () use ($actor, $app, $decision, $stepId, $remarks) {
            /** @var PersonnelApplication $app */
            $app = PersonnelApplication::query()->whereKey($app->id)->lockForUpdate()->firstOrFail();
            $app->load(['currentStep.role', 'type', 'personnel']);
            $step = $app->currentStep;

            // Stage-order guard: the form posts the step it was rendered for.
            if ($app->status !== Status::Pending || ! $step || $step->id !== $stepId) {
                throw ValidationException::withMessages(['decision' => 'This application has already been acted upon or moved to another stage. Refresh the page.']);
            }
            $this->assertCanAct($actor, $app, $step);

            if (in_array($decision, ['return', 'reject'], true) && blank($remarks)) {
                throw ValidationException::withMessages(['remarks' => 'Remarks are required when returning or rejecting an application.']);
            }
            if ($decision === 'recommend' && $step->isFinal()) {
                $decision = 'approve';
            }
            if ($decision === 'approve' && ! $step->isFinal()) {
                $decision = 'recommend';
            }
            if (in_array($decision, ['recommend', 'approve'], true) && $step->requires_documents_complete) {
                $missing = $app->unverifiedRequiredDocuments();
                if ($missing) {
                    throw ValidationException::withMessages(['documents' => 'Required documents must be uploaded and verified before this stage: '.implode(', ', $missing).'.']);
                }
            }

            $from = $app->status;
            switch ($decision) {
                case 'recommend':
                    $next = $step->nextStep();
                    $app->current_step_id = $next?->id;
                    if (! $next) { // defensive: a workflow without final step approves at the end
                        $app->status = Status::Approved;
                        $app->decided_at = now();
                    }
                    $label = 'recommended';
                    break;
                case 'approve':
                    $app->status = Status::Approved;
                    $app->current_step_id = null;
                    $app->decided_at = now();
                    $label = 'approved';
                    break;
                case 'return':
                    $app->status = Status::Returned;
                    $app->current_step_id = null;
                    $label = 'returned';
                    break;
                default:
                    $app->status = Status::Rejected;
                    $app->current_step_id = null;
                    $app->decided_at = now();
                    $label = 'rejected';
            }
            $app->save();

            $this->recordDecision($app, $actor, $label, $from, $app->status, $remarks, $step);
            $this->audit->log('application.'.$label, $app, ['status' => $from->value, 'step' => $step->name], ['status' => $app->status->value], 'Decision at stage "'.$step->name.'"', $remarks);

            if ($app->status === Status::Approved && $app->type->requires_movement) {
                $this->movements->createFromApplication($app, $actor);
            }

            DB::afterCommit(function () use ($app, $label) {
                $fresh = $app->fresh(['currentStep.role', 'personnel.user', 'personnel.rank', 'type', 'creator']);
                $this->notifyApplicant($fresh, $label);
                if ($fresh->status === Status::Pending) {
                    $this->notifyStepActors($fresh);
                }
            });

            return $app;
        });
    }

    public function cancel(User $actor, PersonnelApplication $app, string $reason): PersonnelApplication
    {
        return DB::transaction(function () use ($actor, $app, $reason) {
            $app = PersonnelApplication::query()->whereKey($app->id)->lockForUpdate()->firstOrFail();
            if (in_array($app->status, [Status::Cancelled, Status::Rejected, Status::Closed], true)) {
                throw ValidationException::withMessages(['status' => 'This application can no longer be cancelled.']);
            }
            $movement = $app->movement;
            if ($movement && $movement->actual_departure_at) {
                throw ValidationException::withMessages(['status' => 'Departure has already been recorded. Record the return or a correction instead of cancelling.']);
            }

            $from = $app->status;
            $app->update([
                'status' => Status::Cancelled,
                'current_step_id' => null,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason,
            ]);
            if ($movement) {
                $this->movements->cancel($movement, $actor, 'Application '.$app->reference_number.' cancelled: '.$reason);
            }
            $this->recordDecision($app, $actor, 'cancelled', $from, Status::Cancelled, $reason);
            $this->audit->log('application.cancelled', $app, ['status' => $from->value], ['status' => 'cancelled'], 'Application cancelled', $reason);

            return $app;
        });
    }

    public function close(PersonnelApplication $app, User $actor, ?string $remarks = null): void
    {
        if ($app->status !== Status::Approved) {
            return;
        }
        $app->update(['status' => Status::Closed]);
        $this->recordDecision($app, $actor, 'closed', Status::Approved, Status::Closed, $remarks);
    }

    // ------------------------------------------------------------------ documents

    public function uploadDocument(User $actor, PersonnelApplication $app, UploadedFile $file, ?int $requirementId): ApplicationDocument
    {
        if (! in_array($app->status, [Status::Draft, Status::Returned, Status::Pending], true)) {
            throw ValidationException::withMessages(['file' => 'Documents can no longer be added to this application.']);
        }
        if ($requirementId && ! $app->type->documentRequirements()->whereKey($requirementId)->exists()) {
            throw ValidationException::withMessages(['document_requirement_id' => 'Invalid requirement.']);
        }

        return DB::transaction(function () use ($actor, $app, $file, $requirementId) {
            if ($requirementId) {
                $app->documents()->where('document_requirement_id', $requirementId)->where('is_superseded', false)
                    ->update(['is_superseded' => true]);
            }
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            $path = $file->storeAs('applications/'.$app->id, bin2hex(random_bytes(16)).'.'.$ext, 'local');

            $doc = $app->documents()->create([
                'document_requirement_id' => $requirementId,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
                'storage_path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'checksum' => hash_file('sha256', Storage::disk('local')->path($path)),
                'uploaded_by' => $actor->id,
                'review_status' => 'pending',
            ]);
            $this->audit->log('document.uploaded', $doc, null, ['application' => $app->reference_number ?? $app->id, 'requirement_id' => $requirementId], 'Supporting document uploaded');

            return $doc;
        });
    }

    public function reviewDocument(User $actor, ApplicationDocument $doc, string $status, ?string $remarks): ApplicationDocument
    {
        if (! in_array($status, ['verified', 'rejected'], true)) {
            throw ValidationException::withMessages(['review_status' => 'Invalid review status.']);
        }
        if ($status === 'rejected' && blank($remarks)) {
            throw ValidationException::withMessages(['review_remarks' => 'Give a reason when rejecting a document.']);
        }
        $before = ['review_status' => $doc->review_status];
        $doc->update([
            'review_status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
        ]);
        $this->audit->log('document.'.$status, $doc, $before, ['review_status' => $status], 'Supporting document reviewed', $remarks);

        return $doc;
    }

    // ------------------------------------------------------------------ authority

    /** Whether the user may act on the application's current step. */
    public function canAct(User $user, PersonnelApplication $app): bool
    {
        try {
            $step = $app->currentStep;

            return $app->status === Status::Pending && $step && $this->assertCanAct($user, $app, $step) === null;
        } catch (ValidationException) {
            return false;
        }
    }

    protected function assertCanAct(User $user, PersonnelApplication $app, ApprovalStep $step): void
    {
        if (! $user->hasPermission('applications.act') || ! $user->roles->contains('id', $step->role_id)) {
            throw ValidationException::withMessages(['decision' => 'You are not authorized to act at the "'.$step->name.'" stage.']);
        }
        // No self-approval (separation of duties).
        if ($user->personnel_id && $user->personnel_id === $app->personnel_id) {
            throw ValidationException::withMessages(['decision' => 'You cannot act on your own application.']);
        }
        // The same person may not decide at more than one stage of the same application.
        $actedBefore = $app->decisions()
            ->where('actor_user_id', $user->id)
            ->whereIn('decision', ['recommended', 'approved'])
            ->where('decided_at', '>=', $app->submitted_at ?? $app->created_at)
            ->exists();
        if ($actedBefore) {
            throw ValidationException::withMessages(['decision' => 'You already acted on an earlier stage of this application. Another authorized officer must decide this stage.']);
        }
    }

    // ------------------------------------------------------------------ rules

    /**
     * Server-side business rules (BR-002, BR-003, BR-005, BR-018).
     */
    public function validateBusinessRules(Personnel $personnel, ApplicationType $type, array $data, ?int $ignoreId = null): void
    {
        $errors = [];
        $start = Carbon::parse($data['start_at']);
        $end = Carbon::parse($data['end_at']);

        if (! $type->is_active) {
            $errors['application_type_id'] = 'This application type is not currently enabled.';
        }
        if ($personnel->service_status !== 'active' || ! $personnel->assignmentOn($start)) {
            $errors['personnel_id'] = 'The member has no active assignment covering the requested dates.';
        }
        if ($end->lte($start)) {
            $errors['end_at'] = 'The return date/time must be after the departure date/time.';
        }
        if ($type->max_duration_days && $start->diffInMinutes($end) > $type->max_duration_days * 1440) {
            $errors['end_at'] = 'The requested duration exceeds the configured maximum of '.$type->max_duration_days.' day(s) for '.$type->name.'.';
        }
        if ($type->requires_destination && blank($data['destination'] ?? null)) {
            $errors['destination'] = 'Destination / location is required for '.$type->name.'.';
        }

        $timing = $this->timingFlags($type, $start, now());
        if (($timing['late'] || $timing['retrospective']) && blank($data['late_reason'] ?? null)) {
            $errors['late_reason'] = $timing['retrospective']
                ? 'The start date is in the past. Explain this retrospective request.'
                : 'This request is inside the '.$type->lead_time_days.'-day notice period. Give the reason for the late submission.';
        }

        if (! isset($errors['end_at'])) {
            $overlap = PersonnelApplication::query()->overlapping($personnel->id, $start, $end, $ignoreId)->first();
            if ($overlap) {
                $errors['start_at'] = 'These dates overlap with '.($overlap->reference_number ?? 'another draft').' ('.$overlap->status->label().').';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array{late: bool, retrospective: bool} */
    public function timingFlags(ApplicationType $type, Carbon $start, Carbon $now): array
    {
        $retrospective = $start->lt($now->copy()->startOfDay());
        $late = ! $retrospective && $type->lead_time_days !== null
            && $now->copy()->startOfDay()->addDays($type->lead_time_days)->gt($start);

        return ['late' => $late, 'retrospective' => $retrospective];
    }

    // ------------------------------------------------------------------ helpers

    protected function recordDecision(PersonnelApplication $app, User $actor, string $decision, ?Status $from, ?Status $to, ?string $remarks = null, ?ApprovalStep $step = null): void
    {
        ApplicationDecision::query()->create([
            'application_id' => $app->id,
            'approval_step_id' => $step?->id,
            'actor_user_id' => $actor->id,
            'decision' => $decision,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'remarks' => $remarks,
            'decided_at' => now(),
        ]);
    }

    protected function notifyStepActors(PersonnelApplication $app): void
    {
        if (! $app->currentStep) {
            return;
        }
        $users = User::withRole($app->currentStep->role_id)->get()
            ->filter(fn (User $u) => $u->personnel_id !== $app->personnel_id);
        Notification::send($users, new ApplicationActionRequired($app));
    }

    protected function notifyApplicant(PersonnelApplication $app, string $decision): void
    {
        $recipients = collect([$app->personnel?->user, $app->creator])->filter()->unique('id');
        Notification::send($recipients, new ApplicationStatusChanged($app, $decision));
    }

    public static function uploadRules(): array
    {
        return [
            'file',
            'max:'.Settings::int('upload_max_kb', 5120),
            'mimes:'.Settings::get('upload_types', 'pdf,jpg,jpeg,png'),
        ];
    }
}
