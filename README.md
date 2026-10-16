# MEPTDI Personnel Management and Accountability System (PMAS)

A Laravel 13 web application for the Marine Environmental Protection Training and Development Institute (MEPTDI) to maintain the personnel masterlist, process leave / pass applications through the chain of command, record actual departures and returns, and reconcile daily personnel strength and accountability.

> **Guiding principle:** authorization ≠ accountability. An approved application never marks a member as departed, and a member with no verified status is **never** assumed present.

---

## 1. Quick start (Windows + XAMPP)

Requirements: PHP **8.4+** (the installed Laravel 13 packages require it), Composer, and MySQL/MariaDB from XAMPP (or SQLite for a quick trial). No Node/npm build is needed — styles and scripts are plain files in `public/css` and `public/js`.

Open a terminal in `C:\xampp\htdocs\LaravelProjects\pmas_meptdi` and run:

```bash
composer install                 # only if the vendor folder is missing
php artisan key:generate         # only if APP_KEY in .env is empty
php artisan migrate:fresh --seed # builds all tables + reference data + demo data
php artisan storage:link         # optional; documents use PRIVATE storage and do not need it
php artisan serve
```

Open <http://localhost:8000> and sign in.

> `migrate:fresh` **deletes all existing tables** in the configured database. Run it only on a new/empty development database.

### Demo accounts (APP_ENV=local only)

All demo passwords: `Pmas@2026`

| Username | Role(s) | Use it to… |
|---|---|---|
| `admin` | System Administrator | accounts, roles matrix, application types & workflows, units, ranks, settings, audit trail |
| `dow` | Duty Office Watch | dashboard, accountability board, muster, departures/returns, verification cases, reports |
| `records` | Records Staff | masterlist & import, document verification, duty/training assignments |
| `reviewer` | Immediate Supervisor / Reviewer | approval queue — review stage |
| `approver` | Approving Authority | approval queue — final approval, verify report snapshots |
| `command` | Command Viewer | read-only dashboards and reports |
| `member` | Personnel | own applications and status |

All demo names, service numbers (`DEMO-xxxx`) and units marked "(Sample)" are **fictional**.
With `APP_ENV=production` the seeder creates only one `admin` account with a random temporary password printed in the console.

### Using MySQL (XAMPP) instead of SQLite

1. Start MySQL in the XAMPP Control Panel and create a database, e.g. `pmas_meptdi` (utf8mb4).
2. Edit `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=pmas_meptdi
   DB_USERNAME=root
   DB_PASSWORD=
   APP_TIMEZONE=Asia/Manila
   ```
3. `php artisan config:clear` then `php artisan migrate:fresh --seed`.

### Scheduler (overdue-return checks)

`php artisan pmas:check-overdue` opens verification cases for overdue movements and notifies the Duty Office Watch. It is scheduled every 15 minutes in `routes/console.php`.
- Testing: run `php artisan schedule:work` in a second terminal.
- Server: run `php artisan schedule:run` every minute (cron on Linux, Task Scheduler on Windows).
The Verification Cases page also runs the check when opened, so nothing is missed if the scheduler is not running.

### Automated tests

```bash
php artisan test
```
Feature tests cover access control, the full approval → departure → return lifecycle, separation of duties, stage-order guards, document requirements, sensitive-document restriction, notice-period/overlap rules, accountability classification, overdue detection, conflict flags, reconciliation and historical (point-in-time) strength.

---

## 2. What is included

| Module | Highlights |
|---|---|
| Authentication & accounts | Username/e-mail sign-in, rate-limited, audit-logged; suspension; admin-issued temporary passwords with forced change; e-mail reset link (needs a mail server) |
| Roles & permissions | 7 roles, 24 permissions, editable matrix; enforced server-side with middleware, gates and policies |
| Personnel masterlist | Search/filter, restricted contact fields, assignment history (never overwritten), reassignment/transfer-out, CSV import with validated preview, CSV export |
| Applications | Pass, Ordinary Leave, Sick/Medical (restricted), R&R, Official Business, Training, TDY — all configurable; drafts, submission, reference numbers `CODE-YYYY-#####`, late/retrospective flags, overlap check, cancellation with reason |
| Chain-of-command approval | Versioned, configurable workflows (review → final approval), no self-approval, one person cannot decide two stages, stale-stage protection, decision history with remarks |
| Documents | Versioned checklists, private storage, authorized downloads, verification by records staff, required documents gate the approval stage |
| Movements | Approval creates a *scheduled* movement; DOW records actual departure/return; direct authorized movements; extensions keep the original return; corrections need a reason |
| Accountability | One primary category per member at any instant; muster/roll call; live and point-in-time board; conflict detection; exceptions list |
| Verification cases | Auto-opened for overdue returns; contact-attempt log; resolution by recorded return, authorized extension or confirmed presence |
| Training & duty | Training, schooling, OB, TDY, details; away assignments are tracked as movements |
| Dashboard & calendar | Role-aware dashboard with live as-of time; availability calendar |
| Reports | Daily strength & accountability (print/PDF, CSV, frozen snapshots with verification), unresolved personnel, overdue returns, schedule, application register, pending approvals, training summary, movement history, document compliance |
| Audit trail | Append-only log of sign-ins, approvals, movements, corrections, settings and role changes with before/after values |

### How accountability is calculated (`app/Services/AccountabilityService.php`)

For each member whose assignment covers the reporting time:
1. **Recorded departure, not yet returned** → the movement's category, or **Overdue** once past the expected return + grace period. A "present" muster after departure is flagged as a **conflict**.
2. **Valid confirmation** (muster entry or recorded return within the validity window, default 24 h) → Present / Sick-Medical / Unaccounted.
3. **Approved movement whose departure time passed but was not recorded** → **Unverified**.
4. **Nothing confirmed** → **Unverified** (never assumed present).

`Present + Authorized away + Unresolved = Assigned strength` always.

---

## 3. Decisions still required from MEPTDI

The system intentionally ships with **placeholders** that must be replaced through the Administration screens before operational use:

- Official application types, **notice periods** (currently "not configured"), maximum durations and exceptions.
- Official **document checklist** for each type (current entries are marked "Placeholder").
- The actual **reviewer/approver chain** per type (default: Immediate Supervisor Review → Approving Authority).
- Official unit/section structure (sample units are provided), rank list verification.
- Official daily strength report template, cut-off time convention and signatories.
- Muster validity window and overdue grace period (System Settings).
- Hosting approval, data classification, retention and backup policy before using real personnel data.

---

## 4. Security notes for deployment

- Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS only, `SESSION_SECURE_COOKIE=true`.
- Keep `.env` out of source control; use a dedicated DB user with least privilege.
- Supporting documents are stored in `storage/app/private` (not web-accessible) — include it in encrypted backups.
- Change all demo passwords / do not seed demo data in production.
- Test backup restoration and document the manual continuity procedure for outages.

---

## 5. Project structure (key files)

```
app/Enums/                      AccountabilityCategory, ApplicationStatus, MovementStatus, DutyType
app/Services/                   AccountabilityService, ApplicationWorkflowService, MovementService,
                                VerificationService, ReportService, AuditLogger
app/Policies/                   PersonnelApplicationPolicy, PersonnelPolicy
app/Http/Middleware/            RequirePermission, EnsureAccountIsActive, EnsurePasswordIsChanged, SecurityHeaders
app/Http/Controllers/           Dashboard, Personnel(+Import), Application(+Document), Movement,
                                Accountability, VerificationCase, DutyAssignment, Calendar, Report, Admin/*
app/Console/Commands/           CheckOverdueMovements (pmas:check-overdue)
database/migrations/            2026_10_16_0001xx … 0005xx (RBAC, organization, applications, accountability, reporting/audit)
database/seeders/               CoreSeeder (reference data), DemoSeeder (fictional demo data)
resources/views/                Blade views (layouts, components, modules)
public/css/app.css, public/js/app.js   UI design system (no build step)
tests/Feature/                  AccessControlTest, ApplicationWorkflowTest, AccountabilityTest
```
#   p m a s _ m e p t d i  
 