<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus as Status;
use App\Enums\DutyType;
use App\Enums\MovementStatus;
use App\Models\AccountabilityEvent;
use App\Models\ApplicationDecision;
use App\Models\ApplicationType;
use App\Models\DutyAssignment;
use App\Models\Personnel;
use App\Models\PersonnelApplication;
use App\Models\PersonnelAssignment;
use App\Models\PersonnelMovement;
use App\Models\Rank;
use App\Models\ReferenceSequence;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\VerificationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * FICTIONAL demonstration data for development and user-acceptance walkthroughs.
 * Names, service numbers and units are invented. Never run in production.
 */
class DemoSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Pmas@2026';

    protected array $users = [];

    protected Carbon $now;

    protected Carbon $muster;

    public function run(): void
    {
        $this->now = now()->copy()->second(0);
        $this->muster = $this->now->copy()->setTime(7, 30);
        if ($this->muster->gt($this->now->copy()->subMinutes(30))) {
            $this->muster->subDay();
        }

        $units = $this->seedUnits();
        $people = $this->seedPersonnel($units);
        $this->seedUsers($people);
        $this->seedActivity($people);

        app(VerificationService::class)->openOverdueCases();
    }

    protected function seedUnits(): array
    {
        $root = Unit::query()->create(['code' => 'HQ', 'name' => 'Office of the Director (Sample)', 'sort_order' => 1]);
        $data = [
            ['ADMIN', 'Administrative Section (Sample)'],
            ['TRAIN', 'Training & Instruction Section (Sample)'],
            ['OPS', 'Operations & Plans Section (Sample)'],
            ['LOG', 'Logistics Section (Sample)'],
            ['DOW', 'Duty Office Watch (Sample)'],
        ];
        $units = ['HQ' => $root];
        foreach ($data as $i => [$code, $name]) {
            $units[$code] = Unit::query()->create(['parent_id' => $root->id, 'code' => $code, 'name' => $name, 'sort_order' => $i + 2]);
        }

        return $units;
    }

    protected function seedPersonnel(array $units): array
    {
        $first = ['Juan', 'Maria', 'Jose', 'Ana', 'Mark', 'Kristine', 'Paolo', 'Joy', 'Rafael', 'Liza', 'Carlo', 'Grace', 'Miguel', 'Rowena',
            'Jerome', 'Angelica', 'Ramon', 'Clarisse', 'Noel', 'Patricia', 'Arnel', 'Hazel', 'Dennis', 'Camille', 'Ronald', 'Shiela',
            'Victor', 'Janine', 'Edgar', 'Rochelle', 'Francis', 'Aileen', 'Gilbert', 'Mylene', 'Christian', 'Lorna', 'Rodel', 'Joanna',
            'Allan', 'Marites', 'Benjie', 'Precious', 'Ariel', 'Katrina', 'Nestor', 'Rhea', 'Jayson', 'Diane', 'Oliver', 'Ivy',
            'Reynaldo', 'Charmaine', 'Elmer', 'Lovely', 'Gerald', 'Princess', 'Marlon', 'Cherry', 'Bryan', 'Abigail', 'Leo', 'Faith', 'Danilo', 'Erika'];
        $last = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Villanueva', 'Ramos', 'Aquino', 'Castillo',
            'Navarro', 'Domingo', 'Salazar', 'Mercado', 'Aguilar', 'Pascual', 'Soriano', 'Gonzales', 'Lim', 'Tan', 'Del Rosario', 'De Leon',
            'Manalo', 'Dizon', 'Valdez', 'Rivera', 'Fernandez', 'Lopez', 'Gomez', 'Morales', 'Flores', 'Castro', 'Marquez', 'Robles',
            'Fajardo', 'Pineda', 'Agustin', 'Samson', 'Velasco', 'Cabrera', 'Dela Cruz', 'Tolentino', 'Ignacio', 'Javier', 'Galang',
            'Sison', 'Padilla', 'Bernardo', 'Lacson', 'Esguerra', 'Macaraeg', 'Quiambao', 'Yap', 'Abad', 'Bonifacio', 'Corpuz', 'Evangelista', 'Hernandez', 'Medina', 'Nepomuceno', 'Ortiz', 'Panganiban'];
        $middle = ['Alvarez', 'Bernal', 'Concepcion', 'Diaz', 'Estrada', 'Francisco', 'Guevarra', 'Ilagan', 'Jimenez', 'Katigbak', 'Luna', 'Mañalac'];

        $plan = [ // rank => [count, unit codes cycle, designations]
            'CAPT' => [1, ['HQ'], ['Director']],
            'CDR' => [1, ['HQ'], ['Deputy Director']],
            'LCDR' => [2, ['TRAIN', 'OPS'], ['Chief, Training & Instruction', 'Chief, Operations & Plans']],
            'LT' => [4, ['ADMIN', 'TRAIN', 'LOG', 'OPS'], ['Chief, Administrative Section', 'Course Director', 'Chief, Logistics Section', 'Plans Officer']],
            'LTJG' => [4, ['TRAIN', 'OPS', 'TRAIN', 'DOW'], ['Instructor', 'Operations Officer', 'Instructor', 'Duty Officer']],
            'ENS' => [4, ['TRAIN', 'ADMIN', 'OPS', 'LOG'], ['Instructor', 'Personnel Officer', 'Assistant Plans Officer', 'Supply Officer']],
            'MCPO' => [1, ['HQ'], ['Command Master Chief']],
            'SCPO' => [2, ['ADMIN', 'TRAIN'], ['Admin Chief', 'Training Chief']],
            'CPO' => [4, ['LOG', 'OPS', 'TRAIN', 'DOW'], ['Supply Chief', 'Operations Chief', 'Senior Instructor', 'Duty Chief']],
            'PO1' => [6, ['ADMIN', 'DOW', 'TRAIN', 'LOG', 'OPS', 'DOW'], ['Records NCO', 'Duty NCO', 'Instructor', 'Supply NCO', 'Operations NCO', 'Duty NCO']],
            'PO2' => [8, ['ADMIN', 'TRAIN', 'OPS', 'LOG', 'DOW', 'TRAIN', 'ADMIN', 'OPS'], ['Admin Clerk', 'Training Assistant', 'Operations Clerk', 'Supply Clerk', 'Watchstander', 'Training Assistant', 'Records Clerk', 'Radio Operator']],
            'PO3' => [9, ['TRAIN', 'LOG', 'DOW', 'OPS', 'ADMIN', 'TRAIN', 'LOG', 'DOW', 'OPS'], ['Training Assistant', 'Storekeeper', 'Watchstander', 'Operations Clerk', 'Admin Clerk', 'Lab Assistant', 'Driver', 'Watchstander', 'Communications Clerk']],
            'SN1' => [9, ['LOG', 'DOW', 'TRAIN', 'ADMIN', 'OPS', 'LOG', 'DOW', 'TRAIN', 'LOG'], ['Utility', 'Watchstander', 'Training Aide', 'Messenger', 'Clerk', 'Driver', 'Watchstander', 'Training Aide', 'Utility']],
            'SN2' => [7, ['DOW', 'LOG', 'TRAIN', 'OPS', 'ADMIN', 'LOG', 'DOW'], ['Watchstander', 'Utility', 'Training Aide', 'Clerk', 'Messenger', 'Utility', 'Watchstander']],
            'ASN' => [2, ['LOG', 'DOW'], ['Utility', 'Watchstander']],
        ];

        $ranks = Rank::query()->pluck('id', 'abbreviation');
        $people = [];
        $n = 0;
        foreach ($plan as $abbr => [$count, $unitCodes, $designations]) {
            for ($i = 0; $i < $count; $i++) {
                $f = $first[$n % count($first)];
                $l = $last[($n * 7 + 3) % count($last)];
                $p = Personnel::query()->create([
                    'service_number' => sprintf('DEMO-%04d', 1001 + $n),
                    'rank_id' => $ranks[$abbr],
                    'first_name' => $f,
                    'middle_name' => $middle[$n % count($middle)],
                    'last_name' => $l,
                    'sex' => in_array($f, ['Maria', 'Ana', 'Kristine', 'Joy', 'Liza', 'Grace', 'Rowena', 'Angelica', 'Clarisse', 'Patricia', 'Hazel', 'Camille', 'Shiela', 'Janine', 'Rochelle', 'Aileen', 'Mylene', 'Lorna', 'Joanna', 'Marites', 'Precious', 'Katrina', 'Rhea', 'Diane', 'Ivy', 'Charmaine', 'Lovely', 'Princess', 'Cherry', 'Abigail', 'Faith', 'Erika'], true) ? 'F' : 'M',
                    'designation' => $designations[$i % count($designations)],
                    'email' => strtolower(str_replace(' ', '', $f.'.'.$l)).'@example.test',
                    'contact_number' => '0917'.str_pad((string) (1000000 + $n * 7919 % 8999999), 7, '0', STR_PAD_LEFT),
                    'emergency_contact_name' => 'Demo Contact '.($n + 1),
                    'emergency_contact_number' => '0918'.str_pad((string) (2000000 + $n * 104729 % 7999999), 7, '0', STR_PAD_LEFT),
                    'service_status' => 'active',
                ]);
                $unit = $units[$unitCodes[$i % count($unitCodes)]];
                PersonnelAssignment::query()->create([
                    'personnel_id' => $p->id,
                    'unit_id' => $unit->id,
                    'assignment_type' => 'regular',
                    'start_date' => $this->now->copy()->subMonths(6 + ($n % 30))->startOfMonth(),
                    'order_reference' => 'SAMPLE-ORDER-'.(100 + $n),
                ]);
                $people[] = $p;
                $n++;
            }
        }

        // One member transferred in recently (history: previous assignment closed).
        $mover = $people[30];
        $old = $mover->assignments()->first();
        $old->update(['end_date' => $this->now->copy()->subDays(20)->toDateString(), 'end_reason' => 'Reassigned within MEPTDI']);
        PersonnelAssignment::query()->create([
            'personnel_id' => $mover->id, 'unit_id' => $units['OPS']->id, 'assignment_type' => 'regular',
            'start_date' => $this->now->copy()->subDays(19)->toDateString(), 'order_reference' => 'SAMPLE-ORDER-900',
        ]);

        // One former member (transferred out last month) – visible in history, not in current strength.
        $former = Personnel::query()->create([
            'service_number' => 'DEMO-0999', 'rank_id' => $ranks['PO3'], 'first_name' => 'Romeo', 'middle_name' => 'Luna',
            'last_name' => 'Buenaventura', 'sex' => 'M', 'designation' => 'Watchstander', 'service_status' => 'transferred',
        ]);
        PersonnelAssignment::query()->create([
            'personnel_id' => $former->id, 'unit_id' => $units['DOW']->id, 'start_date' => $this->now->copy()->subYear()->toDateString(),
            'end_date' => $this->now->copy()->subDays(35)->toDateString(), 'end_reason' => 'Transferred to another PCG unit', 'order_reference' => 'SAMPLE-ORDER-777',
        ]);

        return $people;
    }

    protected function seedUsers(array $people): void
    {
        $roles = Role::query()->pluck('id', 'slug');
        $accounts = [
            // username, name, personnel index, roles
            ['admin', 'System Administrator', null, [Role::ADMIN]],
            ['dow', null, 22, [Role::DUTY_WATCH, Role::PERSONNEL]],
            ['records', null, 21, [Role::RECORDS, Role::PERSONNEL]],
            ['reviewer', null, 4, [Role::REVIEWER, Role::PERSONNEL]],
            ['approver', null, 1, [Role::APPROVER, Role::PERSONNEL]],
            ['command', null, 0, [Role::COMMAND, Role::PERSONNEL]],
            ['member', null, 33, [Role::PERSONNEL]],
        ];
        foreach ($accounts as [$username, $name, $idx, $roleSlugs]) {
            $person = $idx !== null ? $people[$idx] : null;
            $user = User::query()->create([
                'username' => $username,
                'name' => $name ?? $person->display_name,
                'email' => $username.'@pmas.example.test',
                'password' => self::DEMO_PASSWORD,
                'personnel_id' => $person?->id,
                'is_active' => true,
                'must_change_password' => false,
                'password_changed_at' => now(),
            ]);
            $user->roles()->sync(collect($roleSlugs)->map(fn ($s) => $roles[$s]));
            $this->users[$username] = $user;
        }
        // Second reviewer so separation-of-duties works for the reviewer's own section
        $u = User::query()->create([
            'username' => 'reviewer2', 'name' => $people[5]->display_name, 'email' => 'reviewer2@pmas.example.test',
            'password' => self::DEMO_PASSWORD, 'personnel_id' => $people[5]->id, 'password_changed_at' => now(),
        ]);
        $u->roles()->sync([$roles[Role::REVIEWER], $roles[Role::PERSONNEL]]);
        $this->users['reviewer2'] = $u;
    }

    protected function seedActivity(array $people): void
    {
        $t = ApplicationType::query()->with('workflows.steps', 'documentRequirements')->get()->keyBy('code');
        $dow = $this->users['dow'];
        $now = $this->now;
        $muster = $this->muster;

        // Indices reserved for specific scenarios (others are mustered present).
        $scenario = [
            'leave' => [7, 15, 26, 38, 47, 55],
            'pass' => [9, 29, 50],
            'overdue' => [41],
            'sick' => [18, 44],
            'training' => [12, 25, 36],
            'official' => [6, 31],
            'pending_departure' => [53],
            'not_mustered' => [58, 60, 62],
            'absent' => [57],
        ];
        $special = array_merge(...array_values($scenario));

        // Morning muster: everyone not in a special scenario (and those departing after muster).
        $presentIds = [];
        foreach ($people as $i => $p) {
            if (! in_array($i, $special, true) || in_array($i, array_merge($scenario['pass'], $scenario['pending_departure']), true)) {
                $presentIds[] = $p->id;
            }
        }
        foreach ($presentIds as $id) {
            $this->event($id, 'present', $muster, $dow);
        }
        foreach ($scenario['sick'] as $i) {
            $this->event($people[$i]->id, 'sick_call', $muster, $dow, 'Reported to clinic at morning muster');
        }
        foreach ($scenario['absent'] as $i) {
            $this->event($people[$i]->id, 'absent', $muster, $dow, 'Not present at morning muster; follow-up in progress');
        }

        // Ordinary leave / R&R — departed, not yet due back
        foreach ($scenario['leave'] as $k => $i) {
            $type = $k % 3 === 2 ? $t['RR'] : $t['OL'];
            $start = $now->copy()->subDays(2 + $k % 3)->setTime(8, 0);
            $end = $now->copy()->addDays(2 + $k)->setTime(17, 0);
            $app = $this->application($people[$i], $type, $start, $end, Status::Approved, 'Personal and family matters', ['Batangas City', 'Quezon City', 'Iloilo City', 'Baguio City', 'Cebu City', 'Naga City'][$k]);
            $this->movementFor($app, $start->copy()->addMinutes(15), null, $dow);
        }

        // Passes departed after muster, due back tonight
        foreach ($scenario['pass'] as $k => $i) {
            $start = $muster->copy()->addHours(2 + $k);
            $end = $muster->copy()->setTime(22, 0);
            if ($end->lte($now)) {
                $end = $now->copy()->addHours(4);
            }
            $app = $this->application($people[$i], $t['PASS'], $start, $end, Status::Approved, 'Personal errand', ['Manila', 'Pasay City', 'Makati City'][$k]);
            $this->movementFor($app, $start->copy()->min($now->copy()->subMinutes(10)), null, $dow);
        }

        // Overdue: pass ended yesterday evening, no return recorded
        foreach ($scenario['overdue'] as $i) {
            $start = $now->copy()->subDay()->setTime(13, 0);
            $end = $now->copy()->subDay()->setTime(21, 0);
            $app = $this->application($people[$i], $t['PASS'], $start, $end, Status::Approved, 'Family visit', 'Cavite');
            $this->movementFor($app, $start->copy()->addMinutes(5), null, $dow);
        }

        // Training (duty assignments, away)
        foreach ($scenario['training'] as $k => $i) {
            $duty = DutyAssignment::query()->create([
                'personnel_id' => $people[$i]->id,
                'assignment_type' => $k === 2 ? DutyType::Schooling : DutyType::Training,
                'title' => ['Marine Pollution Response Course (Sample)', 'Oil Spill Response Training (Sample)', 'Officer Candidate Course (Sample)'][$k],
                'location' => ['Coast Guard Training Center (Sample)', 'Subic Bay (Sample)', 'Coast Guard Education Center (Sample)'][$k],
                'start_at' => $now->copy()->subDays(5 + $k)->setTime(8, 0),
                'end_at' => $now->copy()->addDays(10 + $k * 3)->setTime(17, 0),
                'reference_number' => 'SAMPLE-TO-'.(200 + $k),
                'responsible_office' => 'Training & Instruction Section',
                'is_away' => true,
                'status' => 'ongoing',
                'created_by' => $this->users['records']->id,
            ]);
            PersonnelMovement::query()->create([
                'personnel_id' => $duty->personnel_id, 'duty_assignment_id' => $duty->id,
                'category' => $duty->assignment_type->category(), 'destination' => $duty->location,
                'authority_reference' => $duty->reference_number, 'expected_departure_at' => $duty->start_at,
                'expected_return_at' => $duty->end_at, 'original_expected_return_at' => $duty->end_at,
                'actual_departure_at' => $duty->start_at->copy()->addMinutes(20), 'status' => MovementStatus::Departed,
                'departure_recorded_by' => $dow->id, 'created_by' => $dow->id,
            ]);
        }
        // Upcoming in-house training (not away)
        DutyAssignment::query()->create([
            'personnel_id' => $people[16]->id, 'assignment_type' => DutyType::Training, 'title' => 'In-house Records Management Seminar (Sample)',
            'location' => 'MEPTDI Conference Room', 'start_at' => $now->copy()->addDays(3)->setTime(8, 0), 'end_at' => $now->copy()->addDays(3)->setTime(17, 0),
            'reference_number' => 'SAMPLE-MEMO-15', 'is_away' => false, 'status' => 'scheduled', 'created_by' => $this->users['records']->id,
        ]);

        // Official business (applications)
        foreach ($scenario['official'] as $k => $i) {
            $start = $now->copy()->subDays(1)->setTime(7, 0);
            $end = $now->copy()->addDays(1 + $k)->setTime(18, 0);
            $app = $this->application($people[$i], $t['OB'], $start, $end, Status::Approved, 'Coordination meeting with partner agency (sample)', 'PCG National Headquarters, Manila');
            $this->movementFor($app, $start->copy()->addMinutes(10), null, $dow);
        }

        // Approved pass, scheduled start passed, departure not recorded -> unverified
        foreach ($scenario['pending_departure'] as $i) {
            $start = $now->copy()->subMinutes(50);
            $app = $this->application($people[$i], $t['PASS'], $start, $now->copy()->addHours(6), Status::Approved, 'Medical appointment of dependent', 'Manila');
            $this->movementFor($app, null, null, $dow);
        }

        // Upcoming approved (scheduled departures tomorrow)
        foreach ([20, 27] as $k => $i) {
            $start = $now->copy()->addDay()->setTime(8 + $k, 0);
            $app = $this->application($people[$i], $k ? $t['PASS'] : $t['OL'], $start, $start->copy()->addDays($k ? 0 : 4)->setTime(20, 0), Status::Approved, 'Personal matters', $k ? 'Pasig City' : 'Davao City');
            $this->movementFor($app, null, null, $dow);
        }

        // Returned recently (closed) — history
        foreach ([3, 11, 24, 35, 46] as $k => $i) {
            $start = $now->copy()->subDays(12 + $k * 3)->setTime(8, 0);
            $end = $start->copy()->addDays(3)->setTime(17, 0);
            $app = $this->application($people[$i], $k % 2 ? $t['PASS'] : $t['OL'], $start, $end, Status::Approved, 'Personal matters', 'Laguna');
            $this->movementFor($app, $start->copy()->addMinutes(30), $end->copy()->subMinutes($k === 2 ? -95 : 40), $dow);
        }

        // Pending at review stage
        foreach ([[13, 'OL'], [28, 'PASS'], [48, 'RR']] as $k => [$i, $code]) {
            $start = $now->copy()->addDays(5 + $k * 2)->setTime(8, 0);
            $this->application($people[$i], $t[$code], $start, $start->copy()->addDays($code === 'PASS' ? 0 : 5)->setTime(20, 0), Status::Pending, 'Personal and family matters', 'Pampanga', 1, true);
        }
        // Pending at approval stage (recommended by reviewer)
        foreach ([[17, 'OL'], [39, 'TRNG'], [52, 'SL']] as $k => [$i, $code]) {
            $start = $now->copy()->addDays(3 + $k)->setTime(8, 0);
            $this->application($people[$i], $t[$code], $start, $start->copy()->addDays(3)->setTime(17, 0), Status::Pending,
                $code === 'SL' ? 'Scheduled minor procedure and recovery' : ($code === 'TRNG' ? 'Attendance in specialization course (sample)' : 'Wedding of sibling'),
                $code === 'SL' ? 'Hospital (restricted)' : 'Tagaytay City', 2, $k !== 0);
        }
        // Member user's own records: one returned for correction, one draft, one rejected
        $member = $people[33];
        $this->application($member, $t['OL'], $now->copy()->addDays(10)->setTime(8, 0), $now->copy()->addDays(14)->setTime(17, 0), Status::Returned, 'Vacation with family', 'Bohol');
        $this->application($member, $t['PASS'], $now->copy()->addDays(6)->setTime(13, 0), $now->copy()->addDays(6)->setTime(21, 0), Status::Draft, 'Personal errand', 'Manila');
        $this->application($member, $t['PASS'], $now->copy()->subDays(9)->setTime(13, 0), $now->copy()->subDays(9)->setTime(21, 0), Status::Rejected, 'Personal errand', 'Quezon City');
        $closed = $this->application($member, $t['PASS'], $now->copy()->subDays(20)->setTime(13, 0), $now->copy()->subDays(20)->setTime(21, 0), Status::Approved, 'Personal errand', 'Manila');
        $this->movementFor($closed, $closed->start_at->copy()->addMinutes(10), $closed->end_at->copy()->subMinutes(15), $dow);
    }

    // ---------------------------------------------------------------- helpers

    protected function event(int $personnelId, string $type, Carbon $at, User $by, ?string $remarks = null): void
    {
        AccountabilityEvent::query()->create([
            'personnel_id' => $personnelId, 'event_type' => $type, 'occurred_at' => $at,
            'source' => 'muster', 'recorded_by' => $by->id, 'remarks' => $remarks,
        ]);
    }

    protected function application(Personnel $p, ApplicationType $type, Carbon $start, Carbon $end, Status $status, string $purpose, string $destination, int $atStep = 1, bool $withDocs = true): PersonnelApplication
    {
        $wf = $type->workflows->first();
        $steps = $wf->steps;
        $applicant = User::query()->where('personnel_id', $p->id)->first() ?? $this->users['records'];
        $submitted = $status === Status::Draft ? null : $start->copy()->subDays(4)->setTime(9, 15)->min($this->now->copy()->subHours(3));

        $app = PersonnelApplication::query()->create([
            'personnel_id' => $p->id,
            'application_type_id' => $type->id,
            'approval_workflow_id' => $status === Status::Draft ? null : $wf->id,
            'current_step_id' => $status === Status::Pending ? $steps[$atStep - 1]->id : null,
            'created_by' => $applicant->id,
            'status' => $status,
            'purpose' => $purpose,
            'destination' => $destination,
            'contact_while_away' => $p->contact_number,
            'start_at' => $start,
            'end_at' => $end,
            'submitted_at' => $submitted,
            'decided_at' => in_array($status, [Status::Approved, Status::Rejected], true) ? $submitted?->copy()->addDay()->min($this->now) : null,
        ]);
        ApplicationDecision::query()->create(['application_id' => $app->id, 'actor_user_id' => $applicant->id, 'decision' => 'created', 'to_status' => 'draft', 'decided_at' => $submitted?->copy()->subHour() ?? $this->now->copy()->subHours(2)]);
        if ($status === Status::Draft) {
            return $app;
        }

        $year = (int) $submitted->format('Y');
        $app->update(['reference_number' => sprintf('%s-%d-%05d', $type->code, $year, ReferenceSequence::next($type->code, $year))]);
        $this->decision($app, $applicant, 'submitted', 'draft', 'pending', null, $submitted);

        $reviewer = $this->users['reviewer']->personnel_id === $p->id ? $this->users['reviewer2'] : $this->users['reviewer'];
        $approver = $this->users['approver'];

        if ($withDocs) {
            foreach ($type->documentRequirements as $req) {
                if (! $req->is_required && $status === Status::Pending) {
                    continue;
                }
                $verified = in_array($status, [Status::Approved], true) || ($status === Status::Pending && $atStep === 2);
                $this->fakeDocument($app, $req->id, $applicant, $verified ? 'verified' : 'pending', $verified ? $this->users['records'] : null);
            }
        }

        if ($status === Status::Pending && $atStep === 2 || in_array($status, [Status::Approved, Status::Rejected], true)) {
            $this->decision($app, $reviewer, 'recommended', 'pending', 'pending', 'Recommended for approval.', $submitted->copy()->addHours(3), $steps[0]->id);
        }
        if ($status === Status::Approved) {
            $this->decision($app, $approver, 'approved', 'pending', 'approved', 'Approved.', $submitted->copy()->addHours(20)->min($this->now), $steps[1]->id);
        }
        if ($status === Status::Rejected) {
            $this->decision($app, $approver, 'rejected', 'pending', 'rejected', 'Insufficient manning on the requested date.', $submitted->copy()->addHours(20), $steps[1]->id);
        }
        if ($status === Status::Returned) {
            $app->update(['current_step_id' => null]);
            $this->decision($app, $reviewer, 'returned', 'pending', 'returned', 'Please attach the accomplished application form and indicate a contact number while on leave.', $submitted->copy()->addHours(5), $steps[0]->id);
        }

        return $app;
    }

    protected function decision(PersonnelApplication $app, User $actor, string $decision, ?string $from, ?string $to, ?string $remarks, Carbon $at, ?int $stepId = null): void
    {
        ApplicationDecision::query()->create([
            'application_id' => $app->id, 'approval_step_id' => $stepId, 'actor_user_id' => $actor->id, 'decision' => $decision,
            'from_status' => $from, 'to_status' => $to, 'remarks' => $remarks, 'decided_at' => $at,
        ]);
    }

    protected function movementFor(PersonnelApplication $app, ?Carbon $departed, ?Carbon $returned, User $dow): PersonnelMovement
    {
        $status = $returned ? MovementStatus::Returned : ($departed ? MovementStatus::Departed : MovementStatus::Scheduled);
        $m = PersonnelMovement::query()->create([
            'personnel_id' => $app->personnel_id, 'application_id' => $app->id, 'category' => $app->type->category,
            'destination' => $app->destination, 'authority_reference' => $app->reference_number,
            'expected_departure_at' => $app->start_at, 'expected_return_at' => $app->end_at, 'original_expected_return_at' => $app->end_at,
            'actual_departure_at' => $departed, 'actual_return_at' => $returned, 'status' => $status,
            'departure_recorded_by' => $departed ? $dow->id : null, 'return_recorded_by' => $returned ? $dow->id : null,
            'created_by' => $this->users['approver']->id,
        ]);
        if ($returned) {
            $app->update(['status' => Status::Closed]);
            $this->decision($app, $dow, 'closed', 'approved', 'closed', 'Return recorded '.$returned->format('d M Y H:i'), $returned);
        }

        return $m;
    }

    protected function fakeDocument(PersonnelApplication $app, int $requirementId, User $uploader, string $status, ?User $reviewer): void
    {
        $content = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj 4 0 obj<</Length 74>>stream\nBT /F1 18 Tf 72 700 Td (SAMPLE DOCUMENT - DEMO DATA ONLY) Tj ET\nendstream endobj 5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
        $path = 'applications/'.$app->id.'/'.bin2hex(random_bytes(16)).'.pdf';
        Storage::disk('local')->put($path, $content);
        $app->documents()->create([
            'document_requirement_id' => $requirementId,
            'original_name' => 'sample-document-'.$app->id.'.pdf',
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($content),
            'checksum' => hash('sha256', $content),
            'uploaded_by' => $uploader->id,
            'review_status' => $status,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => $reviewer ? $this->now->copy()->subHours(6) : null,
        ]);
    }
}
