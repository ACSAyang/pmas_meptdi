<?php

namespace Database\Seeders;

use App\Enums\AccountabilityCategory as Cat;
use App\Models\ApplicationType;
use App\Models\ApprovalWorkflow;
use App\Models\Permission;
use App\Models\Rank;
use App\Models\Role;
use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * Reference data required in every environment: permissions, roles, ranks,
 * application types with placeholder checklists/workflows, and settings.
 *
 * IMPORTANT: application types, checklists, lead times and approval chains are
 * placeholders. Replace them with official MEPTDI policy via
 * Administration > Application Types before operational use.
 */
class CoreSeeder extends Seeder
{
    public const PERMISSIONS = [
        // group => [slug => name]
        'Dashboard' => [
            'dashboard.operations' => 'View operations dashboard (strength & accountability)',
        ],
        'Personnel' => [
            'personnel.view' => 'View personnel masterlist',
            'personnel.manage' => 'Create and edit personnel and assignments',
            'personnel.view_sensitive' => 'View restricted contact / emergency information',
            'personnel.import' => 'Import masterlist from CSV',
        ],
        'Applications' => [
            'applications.create' => 'Submit own applications',
            'applications.create_on_behalf' => 'Prepare applications on behalf of members',
            'applications.view_all' => 'View all applications (operational scope)',
            'applications.act' => 'Act on approval stages assigned to own role',
            'applications.cancel_approved' => 'Cancel approved applications (before departure)',
            'documents.verify' => 'Verify supporting documents',
            'medical.view' => 'View medical / sensitive application details',
        ],
        'Movements & Accountability' => [
            'movements.view' => 'View movements',
            'movements.record' => 'Record departures, returns and direct movements',
            'movements.correct' => 'Correct movements and authorize extensions',
            'accountability.view' => 'View accountability status',
            'accountability.muster' => 'Record musters / accountability entries',
            'accountability.verify' => 'Manage verification cases',
            'duty.view' => 'View training & duty assignments',
            'duty.manage' => 'Manage training & duty assignments',
        ],
        'Reports' => [
            'reports.view' => 'View and print reports',
            'reports.generate' => 'Save report snapshots',
            'reports.certify' => 'Certify (verify) saved reports',
        ],
        'Administration' => [
            'users.manage' => 'Manage user accounts and roles',
            'settings.manage' => 'Manage configuration (types, workflows, units, ranks, settings)',
            'audit.view' => 'View audit trail',
        ],
    ];

    public const ROLES = [
        Role::ADMIN => ['System Administrator', 'Manages accounts, roles, configuration. Does not confer approval authority.', [
            'dashboard.operations', 'personnel.view', 'personnel.manage', 'personnel.import', 'applications.view_all',
            'movements.view', 'accountability.view', 'duty.view', 'reports.view', 'users.manage', 'settings.manage', 'audit.view',
        ]],
        Role::DUTY_WATCH => ['Duty Office Watch', 'Monitors strength, records movements and musters, generates reports.', [
            'dashboard.operations', 'personnel.view', 'applications.create', 'applications.create_on_behalf', 'applications.view_all',
            'movements.view', 'movements.record', 'movements.correct', 'accountability.view', 'accountability.muster',
            'accountability.verify', 'duty.view', 'reports.view', 'reports.generate',
        ]],
        Role::PERSONNEL => ['Personnel', 'Submits own applications and views own records.', [
            'applications.create',
        ]],
        Role::REVIEWER => ['Immediate Supervisor / Reviewer', 'Reviews and recommends applications at the review stage.', [
            'applications.create', 'applications.act', 'personnel.view', 'accountability.view', 'dashboard.operations',
        ]],
        Role::APPROVER => ['Approving Authority', 'Final approval or rejection within delegated authority.', [
            'applications.create', 'applications.act', 'applications.cancel_approved', 'applications.view_all', 'medical.view',
            'personnel.view', 'dashboard.operations', 'accountability.view', 'movements.view', 'duty.view',
            'reports.view', 'reports.certify',
        ]],
        Role::RECORDS => ['Records Staff', 'Maintains the masterlist, verifies documents and prepares reports.', [
            'personnel.view', 'personnel.manage', 'personnel.view_sensitive', 'personnel.import', 'applications.create',
            'applications.create_on_behalf', 'applications.view_all', 'documents.verify', 'medical.view',
            'movements.view', 'movements.record', 'accountability.view', 'duty.view', 'duty.manage',
            'dashboard.operations', 'reports.view', 'reports.generate', 'reports.certify',
        ]],
        Role::COMMAND => ['Command Viewer', 'Read-only access to approved dashboards and reports.', [
            'dashboard.operations', 'accountability.view', 'personnel.view', 'movements.view', 'duty.view', 'reports.view',
        ]],
    ];

    /** Philippine Coast Guard ranks (verify abbreviations against current usage). */
    public const RANKS = [
        ['Admiral', 'ADM', 'officer'], ['Vice Admiral', 'VADM', 'officer'], ['Rear Admiral', 'RADM', 'officer'],
        ['Commodore', 'COMMO', 'officer'], ['Captain', 'CAPT', 'officer'], ['Commander', 'CDR', 'officer'],
        ['Lieutenant Commander', 'LCDR', 'officer'], ['Lieutenant', 'LT', 'officer'],
        ['Lieutenant Junior Grade', 'LTJG', 'officer'], ['Ensign', 'ENS', 'officer'],
        ['Master Chief Petty Officer', 'MCPO', 'enlisted'], ['Senior Chief Petty Officer', 'SCPO', 'enlisted'],
        ['Chief Petty Officer', 'CPO', 'enlisted'], ['Petty Officer First Class', 'PO1', 'enlisted'],
        ['Petty Officer Second Class', 'PO2', 'enlisted'], ['Petty Officer Third Class', 'PO3', 'enlisted'],
        ['Seaman First Class', 'SN1', 'enlisted'], ['Seaman Second Class', 'SN2', 'enlisted'],
        ['Apprentice Seaman', 'ASN', 'enlisted'],
    ];

    public function run(): void
    {
        // Permissions
        foreach (self::PERMISSIONS as $group => $perms) {
            foreach ($perms as $slug => $name) {
                Permission::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'group' => $group]);
            }
        }
        $permIds = Permission::query()->pluck('id', 'slug');

        // Roles
        foreach (self::ROLES as $slug => [$name, $description, $perms]) {
            $role = Role::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'is_system' => true]);
            $role->permissions()->sync(collect($perms)->map(fn ($p) => $permIds[$p])->all());
        }

        // Ranks
        foreach (self::RANKS as $i => [$name, $abbr, $category]) {
            Rank::query()->updateOrCreate(['abbreviation' => $abbr], ['name' => $name, 'category' => $category, 'sort_order' => ($i + 1) * 10]);
        }

        $this->seedApplicationTypes();

        foreach (Settings::DEFAULTS as $key => $value) {
            \App\Models\Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
        Settings::flush();
    }

    protected function seedApplicationTypes(): void
    {
        $reviewer = Role::query()->where('slug', Role::REVIEWER)->value('id');
        $approver = Role::query()->where('slug', Role::APPROVER)->value('id');
        $placeholder = 'Placeholder — replace with the official MEPTDI requirement.';

        $types = [
            ['PASS', 'Pass', Cat::Pass, 'Short authorized absence from the unit.', true, false, 'sky', [
                ['Accomplished pass slip (official form)', true, false],
            ]],
            ['OL', 'Ordinary Leave', Cat::Leave, 'Ordinary leave of absence.', true, false, 'blue', [
                ['Accomplished leave application form (official form)', true, false],
                ['Supporting document (if applicable)', false, false],
            ]],
            ['SL', 'Sick Leave / Medical', Cat::Sick, 'Medical-related absence. Details are restricted.', false, true, 'violet', [
                ['Medical certificate / clinic referral', true, true],
            ]],
            ['RR', 'Rest and Recreation (R&R)', Cat::Leave, 'Rest and recreation leave.', true, false, 'blue', [
                ['Accomplished R&R application form (official form)', true, false],
            ]],
            ['OB', 'Official Business', Cat::Official, 'Authorized official business outside the unit.', true, false, 'indigo', [
                ['Office order / travel authority', true, false],
            ]],
            ['TRNG', 'Training / Schooling', Cat::Training, 'Attendance in authorized training or schooling.', true, false, 'teal', [
                ['Training order / directive', true, false],
            ]],
            ['TDY', 'Temporary Duty', Cat::Official, 'Temporary duty with another office or unit.', true, false, 'indigo', [
                ['TDY order', true, false],
            ]],
        ];

        foreach ($types as $i => [$code, $name, $cat, $desc, $destination, $sensitive, $color, $requirements]) {
            $type = ApplicationType::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'description' => $desc,
                'category' => $cat,
                'lead_time_days' => null,      // not configured until MEPTDI confirms
                'max_duration_days' => null,
                'requires_destination' => $destination,
                'requires_movement' => true,
                'is_sensitive' => $sensitive,
                'allow_on_behalf' => true,
                'color' => $color,
                'is_active' => true,
                'sort_order' => ($i + 1) * 10,
            ]);

            if ($type->documentRequirements()->doesntExist()) {
                foreach ($requirements as $j => [$reqName, $required, $reqSensitive]) {
                    $type->documentRequirements()->create([
                        'name' => $reqName,
                        'description' => $placeholder,
                        'is_required' => $required,
                        'is_sensitive' => $reqSensitive,
                        'effective_from' => '2026-01-01',
                        'sort_order' => $j,
                    ]);
                }
            }

            if ($type->workflows()->doesntExist()) {
                $wf = ApprovalWorkflow::query()->create([
                    'application_type_id' => $type->id,
                    'version' => 1,
                    'name' => $name.' – standard routing (v1)',
                    'is_active' => true,
                    'active_from' => now(),
                ]);
                $wf->steps()->create(['step_order' => 1, 'name' => 'Immediate Supervisor Review', 'action_type' => 'review', 'role_id' => $reviewer]);
                $wf->steps()->create(['step_order' => 2, 'name' => 'Approving Authority', 'action_type' => 'approve', 'role_id' => $approver, 'requires_documents_complete' => true]);
            }
        }
    }
}
