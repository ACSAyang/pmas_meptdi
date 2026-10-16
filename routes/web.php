<?php

use App\Http\Controllers\AccountabilityController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationDocumentController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DutyAssignmentController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\PersonnelImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VerificationCaseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| MEPTDI Personnel Management and Accountability System — web routes
|--------------------------------------------------------------------------
| Every protected route requires authentication. Fine-grained permissions are
| enforced with the `perm:` middleware and with policies inside controllers.
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');
    Route::get('/forgot-password', [ForgotPasswordController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/account/password', [PasswordController::class, 'edit'])->name('password.change');
    Route::put('/account/password', [PasswordController::class, 'update'])->name('password.change.update');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/my-profile', [ProfileController::class, 'show'])->name('profile');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');

    // Personnel masterlist
    Route::middleware('perm:personnel.import')->group(function () {
        Route::get('/personnel/import', [PersonnelImportController::class, 'create'])->name('personnel.import');
        Route::post('/personnel/import', [PersonnelImportController::class, 'preview'])->name('personnel.import.preview');
        Route::post('/personnel/import/confirm', [PersonnelImportController::class, 'store'])->name('personnel.import.store');
        Route::get('/personnel/import/template', [PersonnelImportController::class, 'template'])->name('personnel.import.template');
    });
    Route::get('/personnel/export', [PersonnelController::class, 'export'])->middleware('perm:personnel.view')->name('personnel.export');
    Route::middleware('perm:personnel.manage')->group(function () {
        Route::get('/personnel/create', [PersonnelController::class, 'create'])->name('personnel.create');
        Route::post('/personnel', [PersonnelController::class, 'store'])->name('personnel.store');
        Route::get('/personnel/{personnel}/edit', [PersonnelController::class, 'edit'])->name('personnel.edit');
        Route::put('/personnel/{personnel}', [PersonnelController::class, 'update'])->name('personnel.update');
        Route::post('/personnel/{personnel}/assignments', [PersonnelController::class, 'reassign'])->name('personnel.reassign');
    });
    Route::get('/personnel', [PersonnelController::class, 'index'])->middleware('perm:personnel.view')->name('personnel.index');
    Route::get('/personnel/{personnel}', [PersonnelController::class, 'show'])->name('personnel.show');

    // Applications & approvals
    Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('/approvals', [ApplicationController::class, 'queue'])->middleware('perm:applications.act')->name('approvals.index');
    Route::get('/applications/create', [ApplicationController::class, 'create'])->name('applications.create');
    Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
    Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::get('/applications/{application}/edit', [ApplicationController::class, 'edit'])->name('applications.edit');
    Route::put('/applications/{application}', [ApplicationController::class, 'update'])->name('applications.update');
    Route::post('/applications/{application}/submit', [ApplicationController::class, 'submit'])->name('applications.submit');
    Route::post('/applications/{application}/decide', [ApplicationController::class, 'decide'])->name('applications.decide');
    Route::post('/applications/{application}/cancel', [ApplicationController::class, 'cancel'])->name('applications.cancel');
    Route::get('/applications/{application}/print', [ApplicationController::class, 'print'])->name('applications.print');
    Route::post('/applications/{application}/documents', [ApplicationDocumentController::class, 'store'])->name('applications.documents.store');
    Route::get('/documents/{document}', [ApplicationDocumentController::class, 'show'])->name('documents.show');
    Route::post('/documents/{document}/review', [ApplicationDocumentController::class, 'review'])->name('documents.review');

    // Movements (departures / returns)
    Route::middleware('perm:movements.view')->group(function () {
        Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');
        Route::get('/movements/{movement}', [MovementController::class, 'show'])->whereNumber('movement')->name('movements.show');
    });
    Route::middleware('perm:movements.record')->group(function () {
        Route::get('/movements/create', [MovementController::class, 'create'])->name('movements.create');
        Route::post('/movements', [MovementController::class, 'store'])->name('movements.store');
        Route::post('/movements/{movement}/depart', [MovementController::class, 'depart'])->name('movements.depart');
        Route::post('/movements/{movement}/return', [MovementController::class, 'return'])->name('movements.return');
    });
    Route::middleware('perm:movements.correct')->group(function () {
        Route::post('/movements/{movement}/extend', [MovementController::class, 'extend'])->name('movements.extend');
        Route::post('/movements/{movement}/correct', [MovementController::class, 'correct'])->name('movements.correct');
        Route::post('/movements/{movement}/cancel', [MovementController::class, 'cancel'])->name('movements.cancel');
    });

    // Accountability
    Route::get('/accountability', [AccountabilityController::class, 'index'])->middleware('perm:accountability.view')->name('accountability.index');
    Route::middleware('perm:accountability.muster')->group(function () {
        Route::get('/muster', [AccountabilityController::class, 'muster'])->name('muster.index');
        Route::post('/muster', [AccountabilityController::class, 'record'])->name('muster.record');
        Route::post('/accountability-events/{event}/void', [AccountabilityController::class, 'void'])->name('events.void');
    });
    Route::middleware('perm:accountability.verify')->group(function () {
        Route::get('/verification', [VerificationCaseController::class, 'index'])->name('verification.index');
        Route::post('/verification', [VerificationCaseController::class, 'store'])->name('verification.store');
        Route::get('/verification/{case}', [VerificationCaseController::class, 'show'])->name('verification.show');
        Route::post('/verification/{case}/actions', [VerificationCaseController::class, 'action'])->name('verification.action');
        Route::post('/verification/{case}/resolve', [VerificationCaseController::class, 'resolve'])->name('verification.resolve');
    });

    // Training & duty assignments
    Route::middleware('perm:duty.view')->group(function () {
        Route::get('/duty', [DutyAssignmentController::class, 'index'])->name('duty.index');
        Route::get('/duty/{duty}', [DutyAssignmentController::class, 'show'])->whereNumber('duty')->name('duty.show');
    });
    Route::middleware('perm:duty.manage')->group(function () {
        Route::get('/duty/create', [DutyAssignmentController::class, 'create'])->name('duty.create');
        Route::post('/duty', [DutyAssignmentController::class, 'store'])->name('duty.store');
        Route::get('/duty/{duty}/edit', [DutyAssignmentController::class, 'edit'])->name('duty.edit');
        Route::put('/duty/{duty}', [DutyAssignmentController::class, 'update'])->name('duty.update');
        Route::post('/duty/{duty}/cancel', [DutyAssignmentController::class, 'cancel'])->name('duty.cancel');
    });

    Route::get('/calendar', [CalendarController::class, 'index'])->middleware('perm:movements.view,accountability.view')->name('calendar');

    // Reports
    Route::middleware('perm:reports.view')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('/strength', [ReportController::class, 'strength'])->name('strength');
        Route::post('/strength/snapshot', [ReportController::class, 'snapshot'])->middleware('perm:reports.generate')->name('snapshot');
        Route::get('/snapshots', [ReportController::class, 'snapshots'])->name('snapshots');
        Route::get('/snapshots/{run}', [ReportController::class, 'showSnapshot'])->name('snapshots.show');
        Route::post('/snapshots/{run}/certify', [ReportController::class, 'certify'])->middleware('perm:reports.certify')->name('snapshots.certify');
        Route::get('/{type}', [ReportController::class, 'show'])->name('show');
    });

    // Administration
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('perm:users.manage')->group(function () {
            Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [Admin\UserController::class, 'create'])->name('users.create');
            Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [Admin\UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [Admin\UserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('/users/{user}/status', [Admin\UserController::class, 'toggle'])->name('users.toggle');
            Route::get('/roles', [Admin\RoleController::class, 'index'])->name('roles.index');
            Route::put('/roles', [Admin\RoleController::class, 'update'])->name('roles.update');
        });
        Route::middleware('perm:settings.manage')->group(function () {
            Route::get('/units', [Admin\UnitController::class, 'index'])->name('units.index');
            Route::post('/units', [Admin\UnitController::class, 'store'])->name('units.store');
            Route::put('/units/{unit}', [Admin\UnitController::class, 'update'])->name('units.update');
            Route::get('/ranks', [Admin\RankController::class, 'index'])->name('ranks.index');
            Route::post('/ranks', [Admin\RankController::class, 'store'])->name('ranks.store');
            Route::put('/ranks/{rank}', [Admin\RankController::class, 'update'])->name('ranks.update');
            Route::get('/application-types', [Admin\ApplicationTypeController::class, 'index'])->name('types.index');
            Route::get('/application-types/create', [Admin\ApplicationTypeController::class, 'create'])->name('types.create');
            Route::post('/application-types', [Admin\ApplicationTypeController::class, 'store'])->name('types.store');
            Route::get('/application-types/{type}/edit', [Admin\ApplicationTypeController::class, 'edit'])->name('types.edit');
            Route::put('/application-types/{type}', [Admin\ApplicationTypeController::class, 'update'])->name('types.update');
            Route::post('/application-types/{type}/requirements', [Admin\ApplicationTypeController::class, 'addRequirement'])->name('types.requirements.store');
            Route::post('/requirements/{requirement}/retire', [Admin\ApplicationTypeController::class, 'retireRequirement'])->name('types.requirements.retire');
            Route::post('/application-types/{type}/workflow', [Admin\ApplicationTypeController::class, 'saveWorkflow'])->name('types.workflow');
            Route::get('/settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        });
        Route::middleware('perm:audit.view')->group(function () {
            Route::get('/audit', [Admin\AuditLogController::class, 'index'])->name('audit.index');
            Route::get('/audit/{log}', [Admin\AuditLogController::class, 'show'])->name('audit.show');
        });
    });
});
