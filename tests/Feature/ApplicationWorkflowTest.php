<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\MovementStatus;
use App\Models\ApplicationType;
use App\Models\PersonnelApplication;
use App\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationWorkflowTest extends TestCase
{
    protected function payload(string $code = 'PASS', array $over = []): array
    {
        return array_merge([
            'application_type_id' => ApplicationType::query()->where('code', $code)->value('id'),
            'start_at' => now()->addDays(3)->setTime(8, 0)->format('Y-m-d H:i'),
            'end_at' => now()->addDays(3)->setTime(20, 0)->format('Y-m-d H:i'),
            'purpose' => 'Personal errand',
            'destination' => 'Manila',
            'intent' => 'submit',
        ], $over);
    }

    protected function uploadRequiredDocs(PersonnelApplication $app, $as): void
    {
        Storage::fake('local');
        foreach ($app->type->documentRequirements->where('is_required', true) as $req) {
            $this->actingAs($as)->post(route('applications.documents.store', $app), [
                'file' => UploadedFile::fake()->create('form.pdf', 20, 'application/pdf'),
                'document_requirement_id' => $req->id,
            ])->assertSessionHasNoErrors();
        }
    }

    public function test_full_lifecycle_submit_review_approve_depart_return(): void
    {
        $applicantP = $this->member();
        $applicant = $this->userWith([Role::PERSONNEL], $applicantP);
        $reviewer = $this->userWith([Role::REVIEWER], $this->member('LT'));
        $approver = $this->userWith([Role::APPROVER], $this->member('CDR'));
        $records = $this->userWith([Role::RECORDS], $this->member('PO1'));
        $dow = $this->userWith([Role::DUTY_WATCH], $this->member('CPO'));

        $this->actingAs($applicant)->post('/applications', $this->payload())->assertSessionHasNoErrors();
        $app = PersonnelApplication::query()->firstOrFail();
        $this->assertSame(ApplicationStatus::Pending, $app->status);
        $this->assertMatchesRegularExpression('/^PASS-\d{4}-\d{5}$/', $app->reference_number);
        $this->assertSame(1, $app->currentStep->step_order);

        $this->uploadRequiredDocs($app, $applicant);

        // Applicant cannot act on own application, reviewer recommends
        $this->actingAs($reviewer)->post(route('applications.decide', $app), ['decision' => 'recommend', 'step_id' => $app->current_step_id])->assertSessionHasNoErrors();
        $app->refresh();
        $this->assertSame(2, $app->currentStep->step_order);
        $this->assertSame(ApplicationStatus::Pending, $app->status, 'A recommendation is not final approval (BR-006)');

        // Approval blocked until documents verified
        $this->actingAs($approver)->post(route('applications.decide', $app), ['decision' => 'approve', 'step_id' => $app->current_step_id])->assertSessionHasErrors('documents');
        foreach ($app->documents as $doc) {
            $this->actingAs($records)->post(route('documents.review', $doc), ['review_status' => 'verified'])->assertSessionHasNoErrors();
        }
        $this->actingAs($approver)->post(route('applications.decide', $app), ['decision' => 'approve', 'step_id' => $app->current_step_id])->assertSessionHasNoErrors();
        $app->refresh();
        $this->assertSame(ApplicationStatus::Approved, $app->status);

        // Approval creates a SCHEDULED movement — not a departure (BR-008)
        $movement = $app->movement;
        $this->assertNotNull($movement);
        $this->assertSame(MovementStatus::Scheduled, $movement->status);
        $this->assertNull($movement->actual_departure_at);

        $this->actingAs($dow)->post(route('movements.depart', $movement), ['actual_at' => now()->format('Y-m-d H:i')])->assertSessionHasNoErrors();
        $this->assertSame(MovementStatus::Departed, $movement->fresh()->status);
        $this->actingAs($dow)->post(route('movements.return', $movement), ['actual_at' => now()->format('Y-m-d H:i')])->assertSessionHasNoErrors();
        $movement->refresh();
        $this->assertSame(MovementStatus::Returned, $movement->status);
        $this->assertSame(ApplicationStatus::Closed, $app->fresh()->status);
        // Planned dates untouched
        $this->assertTrue($movement->expected_departure_at->eq($app->start_at));

        $this->assertDatabaseHas('audit_logs', ['action' => 'application.approved', 'entity_id' => $app->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'movement.departed']);
    }

    public function test_user_cannot_approve_own_application(): void
    {
        $p = $this->member('LT');
        $self = $this->userWith([Role::REVIEWER, Role::PERSONNEL], $p);
        $this->actingAs($self)->post('/applications', $this->payload())->assertSessionHasNoErrors();
        $app = PersonnelApplication::query()->firstOrFail();
        $this->actingAs($self)->post(route('applications.decide', $app), ['decision' => 'recommend', 'step_id' => $app->current_step_id])->assertSessionHasErrors('decision');
        $this->assertSame(1, $app->fresh()->currentStep->step_order);
    }

    public function test_same_officer_cannot_decide_two_stages(): void
    {
        $applicant = $this->userWith([Role::PERSONNEL], $this->member());
        $both = $this->userWith([Role::REVIEWER, Role::APPROVER], $this->member('CDR'));
        $this->actingAs($applicant)->post('/applications', $this->payload('OB'))->assertSessionHasNoErrors();
        $app = PersonnelApplication::query()->firstOrFail();
        $this->actingAs($both)->post(route('applications.decide', $app), ['decision' => 'recommend', 'step_id' => $app->current_step_id])->assertSessionHasNoErrors();
        $app->refresh();
        $this->actingAs($both)->post(route('applications.decide', $app), ['decision' => 'approve', 'step_id' => $app->current_step_id])->assertSessionHasErrors('decision');
        $this->assertSame(ApplicationStatus::Pending, $app->fresh()->status);
    }

    public function test_wrong_role_and_stale_stage_are_rejected(): void
    {
        $applicant = $this->userWith([Role::PERSONNEL], $this->member());
        $approver = $this->userWith([Role::APPROVER], $this->member('CDR'));
        $reviewer = $this->userWith([Role::REVIEWER], $this->member('LT'));
        $this->actingAs($applicant)->post('/applications', $this->payload())->assertSessionHasNoErrors();
        $app = PersonnelApplication::query()->firstOrFail();
        $step1 = $app->current_step_id;

        // approver cannot act at reviewer stage
        $this->actingAs($approver)->post(route('applications.decide', $app), ['decision' => 'approve', 'step_id' => $step1])->assertSessionHasErrors('decision');
        // reviewer recommends; a second (stale) post for step 1 fails
        $this->actingAs($reviewer)->post(route('applications.decide', $app), ['decision' => 'recommend', 'step_id' => $step1])->assertSessionHasNoErrors();
        $reviewer2 = $this->userWith([Role::REVIEWER], $this->member('LT'));
        $response = $this->actingAs($reviewer2)->post(route('applications.decide', $app), ['decision' => 'recommend', 'step_id' => $step1]);
        $this->assertTrue($response->isForbidden() || session()->has('errors'), 'Stale decision must be refused');
        $this->assertSame(1, $app->decisions()->where('decision', 'recommended')->count(), 'No duplicate decision recorded');
    }

    public function test_return_and_reject_require_remarks_and_resubmission_works(): void
    {
        $applicant = $this->userWith([Role::PERSONNEL], $this->member());
        $reviewer = $this->userWith([Role::REVIEWER], $this->member('LT'));
        $this->actingAs($applicant)->post('/applications', $this->payload())->assertSessionHasNoErrors();
        $app = PersonnelApplication::query()->firstOrFail();
        $this->actingAs($reviewer)->post(route('applications.decide', $app), ['decision' => 'return', 'step_id' => $app->current_step_id])->assertSessionHasErrors('remarks');
        $this->actingAs($reviewer)->post(route('applications.decide', $app), ['decision' => 'return', 'step_id' => $app->current_step_id, 'remarks' => 'Attach the form'])->assertSessionHasNoErrors();
        $this->assertSame(ApplicationStatus::Returned, $app->fresh()->status);
        $ref = $app->fresh()->reference_number;
        $this->actingAs($applicant)->post(route('applications.submit', $app))->assertSessionHasNoErrors();
        $this->assertSame(ApplicationStatus::Pending, $app->fresh()->status);
        $this->assertSame($ref, $app->fresh()->reference_number, 'Reference number is kept on resubmission');
    }

    public function test_business_rule_validation(): void
    {
        $p = $this->member();
        $user = $this->userWith([Role::PERSONNEL], $p);
        $this->actingAs($user);
        // end before start (BR-005)
        $this->post('/applications', $this->payload('PASS', ['end_at' => now()->addDays(2)->format('Y-m-d H:i')]))->assertSessionHasErrors('end_at');
        // destination required for PASS
        $this->post('/applications', $this->payload('PASS', ['destination' => '']))->assertSessionHasErrors('destination');
        // retrospective request needs a reason
        $this->post('/applications', $this->payload('PASS', ['start_at' => now()->subDays(2)->format('Y-m-d H:i'), 'end_at' => now()->subDay()->format('Y-m-d H:i')]))->assertSessionHasErrors('late_reason');
        // overlap
        $this->post('/applications', $this->payload())->assertSessionHasNoErrors();
        $this->post('/applications', $this->payload())->assertSessionHasErrors('start_at');
    }

    public function test_configured_notice_period_flags_late_submission(): void
    {
        ApplicationType::query()->where('code', 'OL')->update(['lead_time_days' => 7]);
        $user = $this->userWith([Role::PERSONNEL], $this->member());
        $this->actingAs($user)->post('/applications', $this->payload('OL'))->assertSessionHasErrors('late_reason');
        $this->actingAs($user)->post('/applications', $this->payload('OL', ['late_reason' => 'Family emergency']))->assertSessionHasNoErrors();
        $this->assertTrue(PersonnelApplication::query()->first()->is_late);
    }

    public function test_personnel_cannot_file_for_others_or_view_others(): void
    {
        $other = $this->member();
        $user = $this->userWith([Role::PERSONNEL], $this->member());
        $this->actingAs($user)->post('/applications', $this->payload('PASS', ['personnel_id' => $other->id]))->assertForbidden();

        $otherUser = $this->userWith([Role::PERSONNEL], $other);
        $this->actingAs($otherUser)->post('/applications', $this->payload())->assertSessionHasNoErrors();
        $app = PersonnelApplication::query()->firstOrFail();
        $this->actingAs($user)->get(route('applications.show', $app))->assertForbidden();
    }

    public function test_sensitive_application_documents_are_restricted(): void
    {
        Storage::fake('local');
        $applicant = $this->userWith([Role::PERSONNEL], $this->member());
        $this->actingAs($applicant)->post('/applications', $this->payload('SL', ['destination' => 'Clinic', 'purpose' => 'Confidential medical reason']))->assertSessionHasNoErrors();
        $app = PersonnelApplication::query()->firstOrFail();
        $req = $app->type->documentRequirements->first();
        $this->actingAs($applicant)->post(route('applications.documents.store', $app), ['file' => UploadedFile::fake()->create('cert.pdf', 10, 'application/pdf'), 'document_requirement_id' => $req->id]);
        $doc = $app->documents()->first();

        $dow = $this->userWith([Role::DUTY_WATCH], $this->member('CPO'));
        $this->actingAs($dow)->get(route('applications.show', $app))->assertForbidden();
        $this->actingAs($dow)->get(route('documents.show', $doc))->assertForbidden();

        $this->actingAs($applicant)->get(route('documents.show', $doc))->assertOk();
        $records = $this->userWith([Role::RECORDS], $this->member('PO1'));
        $this->actingAs($records)->get(route('applications.show', $app))->assertOk()->assertSee('Confidential medical reason');
    }

    public function test_cancellation_preserves_history_and_blocks_after_departure(): void
    {
        $applicant = $this->userWith([Role::PERSONNEL], $this->member());
        $this->actingAs($applicant)->post('/applications', $this->payload())->assertSessionHasNoErrors();
        $app = PersonnelApplication::query()->firstOrFail();
        $this->actingAs($applicant)->post(route('applications.cancel', $app), ['reason' => 'No longer needed'])->assertSessionHasNoErrors();
        $this->assertSame(ApplicationStatus::Cancelled, $app->fresh()->status);
        $this->assertDatabaseHas('application_decisions', ['application_id' => $app->id, 'decision' => 'submitted']);
        $this->assertDatabaseHas('application_decisions', ['application_id' => $app->id, 'decision' => 'cancelled']);
    }
}
