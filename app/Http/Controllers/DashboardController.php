<?php

namespace App\Http\Controllers;

use App\Enums\ApplicationStatus;
use App\Enums\MovementStatus;
use App\Models\PersonnelApplication;
use App\Models\PersonnelMovement;
use App\Models\Unit;
use App\Models\VerificationCase;
use App\Services\AccountabilityService;
use App\Support\NavCounts;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, AccountabilityService $accountability): View
    {
        $user = $request->user();
        $data = ['user' => $user];

        if ($user->personnel_id) {
            $data['myStatus'] = $accountability->statusOf($user->personnel);
            $data['myApplications'] = PersonnelApplication::query()->where('personnel_id', $user->personnel_id)
                ->with('type', 'currentStep')->latest('updated_at')->limit(5)->get();
            $data['myMovement'] = PersonnelMovement::query()->where('personnel_id', $user->personnel_id)
                ->whereIn('status', [MovementStatus::Scheduled->value, MovementStatus::Departed->value])
                ->orderBy('expected_departure_at')->first();
        }

        if ($user->hasPermission('applications.act')) {
            $data['queue'] = NavCounts::approvalQueue($user)->with(['personnel.rank', 'type', 'currentStep'])->orderBy('submitted_at')->limit(6)->get();
            $data['queueCount'] = NavCounts::approvalQueue($user)->count();
        }

        if ($user->hasPermission('dashboard.operations')) {
            $unit = $request->filled('unit') ? Unit::query()->find($request->integer('unit')) : null;
            $unitIds = $unit ? $unit->descendantIds() : null;
            $snapshot = $accountability->snapshot(now(), $unitIds);
            $personnelIds = $snapshot->rows->map(fn ($r) => $r->personnel->id);

            $today = now()->startOfDay();
            $tomorrow = now()->endOfDay();

            $data += [
                'snapshot' => $snapshot,
                'units' => Unit::options(),
                'unit' => $unit,
                'departuresDue' => PersonnelMovement::query()
                    ->where('status', MovementStatus::Scheduled->value)
                    ->where('expected_departure_at', '<=', $tomorrow)
                    ->whereIn('personnel_id', $personnelIds)
                    ->with(['personnel.rank', 'application.type', 'dutyAssignment'])
                    ->orderBy('expected_departure_at')->limit(8)->get(),
                'returnsDue' => PersonnelMovement::query()
                    ->where('status', MovementStatus::Departed->value)
                    ->whereBetween('expected_return_at', [$today, $tomorrow])
                    ->whereIn('personnel_id', $personnelIds)
                    ->with(['personnel.rank', 'application.type', 'dutyAssignment'])
                    ->orderBy('expected_return_at')->limit(8)->get(),
                'recentMovements' => PersonnelMovement::query()
                    ->whereNotNull('actual_departure_at')
                    ->whereIn('personnel_id', $personnelIds)
                    ->with(['personnel.rank', 'application.type', 'dutyAssignment'])
                    ->orderByRaw('COALESCE(actual_return_at, actual_departure_at) DESC')
                    ->limit(7)->get(),
                'pendingApplications' => PersonnelApplication::query()->where('status', ApplicationStatus::Pending->value)->count(),
                'returnedApplications' => PersonnelApplication::query()->where('status', ApplicationStatus::Returned->value)->count(),
                'openCases' => VerificationCase::query()->where('status', 'open')->count(),
                'canSeeSensitive' => $user->hasPermission('medical.view'),
            ];
        }

        return view('dashboard.index', $data);
    }
}
