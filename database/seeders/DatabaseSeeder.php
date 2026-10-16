<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * php artisan db:seed            -> reference data (+ demo data when APP_ENV=local)
     * php artisan db:seed --class=CoreSeeder -> reference data only
     */
    public function run(): void
    {
        $this->call(CoreSeeder::class);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);

            return;
        }

        // Non-local environments: create a single administrator with a random
        // temporary password that must be changed at first login.
        if (User::query()->doesntExist()) {
            $password = Str::password(16);
            $admin = User::query()->create([
                'username' => 'admin',
                'name' => 'System Administrator',
                'email' => 'admin@localhost',
                'password' => $password,
                'must_change_password' => true,
            ]);
            $admin->roles()->sync([Role::query()->where('slug', Role::ADMIN)->value('id')]);
            $this->command?->warn("Administrator account created. Username: admin  Temporary password: {$password}");
        }
    }
}
