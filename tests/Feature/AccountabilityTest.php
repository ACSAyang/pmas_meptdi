<?php

namespace Tests\Feature;

use App\Enums\AccountabilityCategory as Cat;
use App\Enums\MovementStatus;
use App\Models\PersonnelAssignment;
use App\Models\PersonnelMovement;
use App\Models\Role;
use App\Models\VerificationCase;
use App\Services\AccountabilityService;
use App\Services\MovementService;
use App\Services\VerificationService;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountabilityTest extends TestCase
{
    protected function svc(): AccountabilityService
    {
        return app(AccountabilityService::class);
    }

    protected function movement($p, $user, array $over = []): PersonnelMovement
    {
        return PersonnelMovement::query()->create(array_merge([
            'personnel_id' => $p->id, 'category' => Cat::Leave, 'authority_reference' => 'TEST',
            'expected_departure_at' => now()->subDay(), 'expected_return_at' => now()->addDays(2),
            'original_expected_return_at' => now()->addDays(2), 'status' => MovementStatus::Scheduled, 'created_by' => $user->id,
        ], $over));
    }

    public function test_member_without_confirmation_is_unverified_not_present(): void
    {
        $p = $this->member();
        $row = $this->svc()->statusOf($p);
        $this->assertSame(Cat::Unverified, $row->category);
    }

    public function test_muster_confirms_presence_within_validity_window(): void
    {
        $p = $this->member();
        $dow = $this->userWith([Role::DUTY_WATCH], $this->member());
        app(MovementService::class)->recordEvents($dow, [$p->id], 'present', now()->subHour(), null);
        $this->assertSame(Cat::Present, $this->svc()->statusOf($p)->category);

        // outside validity window -> unverified again
        $this->travel(Settings::int('muster_validity_hours', 24) + 1)->hours();
        $this->assertSame(Cat::Unverified, $this->svc()->statusOf($p)->category);
    }

    public function test_departed_movement_uses_category_and_overdue_detection(): void
    {
        $p = $this->member();
        $dow = $this->userWith([Role::DUTY_WATCH], $this->member());
        $m = $this->movement($p, $dow, ['expected_departure_at' => now()->subDays(2), 'expected_return_at' => now()->addHours(2), 'original_expected_return_at' => now()->addHours(2)]);
        app(MovementService::class)->recordDeparture($dow, $m, now()->subDays(2));
        $this->assertSame(Cat::Leave, $this->svc()->statusOf($p)->category);

        $this->travel(3)->hours();
        $this->assertSame(Cat::Overdue, $this->svc()->statusOf($p)->category);
        $this->assertSame(1, app(VerificationService::class)->openOverdueCases());
        $this->assertSame(0, app(VerificationService::class)->openOverdueCases(), 'No duplicate cases');

        app(MovementService::class)->recordReturn($dow, $m->fresh(), now());
        $this->assertSame(Cat::Present, $this->svc()->statusOf($p)->category);
        $this->assertSame('resolved', VerificationCase::query()->first()->status);
    }

    public function test_approved_but_not_departed_is_unverified(): void
    {
        $p = $this->member();
        $dow = $this->userWith([Role::DUTY_WATCH], $this->member());
        app(MovementService::class)->recordEvents($dow, [$p->id], 'present', now()->subHours(5), null);
        $this->movement($p, $dow, ['expected_departure_at' => now()->subHour()]);
        $row = $this->svc()->statusOf($p);
        $this->assertSame(Cat::Unverified, $row->category);
        $this->assertStringContainsString('departure not recorded', $row->detail);

        // A muster after the scheduled departure confirms the member did not leave
        app(MovementService::class)->recordEvents($dow, [$p->id], 'present', now()->subMinutes(5), null);
        $this->assertSame(Cat::Present, $this->svc()->statusOf($p)->category);
    }

    public function test_conflicting_records_are_flagged(): void
    {
        $p = $this->member();
        $dow = $this->userWith([Role::DUTY_WATCH], $this->member());
        $m = $this->movement($p, $dow);
        app(MovementService::class)->recordDeparture($dow, $m, now()->subHours(10));
        // force a muster entry after departure (e.g. return not encoded)
        \App\Models\AccountabilityEvent::query()->create(['personnel_id' => $p->id, 'event_type' => 'present', 'occurred_at' => now()->subHour(), 'recorded_by' => $dow->id]);
        $row = $this->svc()->statusOf($p);
        $this->assertSame(Cat::Unverified, $row->category);
        $this->assertTrue($row->conflict);
    }

    public function test_cannot_open_two_movements_at_once(): void
    {
        $p = $this->member();
        $dow = $this->userWith([Role::DUTY_WATCH], $this->member());
        $a = $this->movement($p, $dow);
        $b = $this->movement($p, $dow);
        app(MovementService::class)->recordDeparture($dow, $a, now()->subHour());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MovementService::class)->recordDeparture($dow, $b, now());
    }

    public function test_totals_reconcile_and_each_member_counted_once(): void
    {
        $dow = $this->userWith([Role::DUTY_WATCH], $this->member());
        $people = collect(range(1, 8))->map(fn () => $this->member());
        $ms = app(MovementService::class);
        $ms->recordEvents($dow, $people->take(4)->pluck('id')->all(), 'present', now()->subHour(), null);
        $ms->recordEvents($dow, [$people[4]->id], 'sick_call', now()->subHour(), null);
        $ms->recordEvents($dow, [$people[5]->id], 'absent', now()->subHour(), null);
        $m = $this->movement($people[6], $dow, ['category' => Cat::Pass]);
        $ms->recordDeparture($dow, $m, now()->subHours(2));

        $snap = $this->svc()->snapshot();
        // 8 members + the DOW member = 9 assigned
        $this->assertSame(9, $snap->assigned());
        $this->assertTrue($snap->reconciles());
        $this->assertSame(4, $snap->present());
        $this->assertSame(1, $snap->count(Cat::Sick));
        $this->assertSame(1, $snap->count(Cat::Pass));
        $this->assertSame(1, $snap->count(Cat::Unaccounted));
        $this->assertSame(2, $snap->count(Cat::Unverified));
        $this->assertSame($snap->assigned(), array_sum($snap->counts()));
    }

    public function test_historical_report_uses_assignment_effective_at_that_time(): void
    {
        $p = $this->member('PO2', now()->subYear()->toDateString());
        PersonnelAssignment::query()->where('personnel_id', $p->id)->update(['end_date' => now()->subDays(10)->toDateString()]);
        $newcomer = $this->member('PO3', now()->subDays(3)->toDateString());

        $past = $this->svc()->snapshot(now()->subDays(20));
        $present = $this->svc()->snapshot(now());
        $this->assertTrue($past->rows->contains(fn ($r) => $r->personnel->id === $p->id));
        $this->assertFalse($past->rows->contains(fn ($r) => $r->personnel->id === $newcomer->id));
        $this->assertFalse($present->rows->contains(fn ($r) => $r->personnel->id === $p->id));
        $this->assertTrue($present->rows->contains(fn ($r) => $r->personnel->id === $newcomer->id));
    }

    public function test_corrections_keep_audit_trail(): void
    {
        $p = $this->member();
        $dow = $this->userWith([Role::DUTY_WATCH], $this->member());
        $m = $this->movement($p, $dow);
        app(MovementService::class)->recordDeparture($dow, $m, now()->subHours(3));
        $this->actingAs($dow)->post(route('movements.correct', $m), ['actual_return_at' => now()->subHour()->format('Y-m-d H:i'), 'reason' => 'Return not encoded on time'])->assertSessionHasNoErrors();
        $this->assertSame(MovementStatus::Returned, $m->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'movement.corrected', 'reason' => 'Return not encoded on time']);
    }

    public function test_dashboard_board_and_reports_render(): void
    {
        $dow = $this->userWith([Role::DUTY_WATCH], $this->member());
        $this->actingAs($dow);
        foreach (['/', '/accountability', '/muster', '/movements', '/calendar', '/reports/strength', '/reports/unresolved', '/reports/register', '/reports/movements'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->post(route('reports.snapshot'))->assertRedirect();
        $this->assertDatabaseCount('report_runs', 1);
    }
}
