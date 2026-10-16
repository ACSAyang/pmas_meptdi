<?php

namespace Tests;

use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\Rank;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Console output is not asserted in these tests. */
    public $mockConsoleOutput = false;

    protected Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CoreSeeder::class);
        $this->unit = Unit::query()->create(['code' => 'TST', 'name' => 'Test Section']);
    }

    protected function member(string $rank = 'PO2', ?string $startDate = null, ?Unit $unit = null): Personnel
    {
        static $n = 0;
        $n++;
        $p = Personnel::query()->create([
            'service_number' => 'T-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT).'-'.uniqid(),
            'rank_id' => Rank::query()->where('abbreviation', $rank)->value('id'),
            'first_name' => 'Test'.$n,
            'last_name' => 'Member'.$n,
            'service_status' => 'active',
        ]);
        PersonnelAssignment::query()->create([
            'personnel_id' => $p->id,
            'unit_id' => ($unit ?? $this->unit)->id,
            'start_date' => $startDate ?? now()->subYear()->toDateString(),
        ]);

        return $p;
    }

    protected function userWith(array $roles, ?Personnel $personnel = null): User
    {
        static $u = 0;
        $u++;
        $user = User::query()->create([
            'username' => 'user'.$u.uniqid(),
            'name' => 'User '.$u,
            'email' => 'user'.$u.uniqid().'@test.local',
            'password' => 'Secret#12345',
            'personnel_id' => $personnel?->id,
        ]);
        $user->roles()->sync(Role::query()->whereIn('slug', $roles)->pluck('id'));

        return $user->fresh();
    }
}
