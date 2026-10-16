<?php

namespace App\Providers;

use App\Models\Personnel;
use App\Models\PersonnelApplication;
use App\Models\User;
use App\Policies\PersonnelApplicationPolicy;
use App\Policies\PersonnelPolicy;
use App\Support\Settings;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\AuditLogger::class);
    }

    public function boot(): void
    {
        Gate::policy(PersonnelApplication::class, PersonnelApplicationPolicy::class);
        Gate::policy(Personnel::class, PersonnelPolicy::class);

        // Permission slugs (e.g. "reports.view") are granted through roles.
        Gate::before(function (User $user, string $ability) {
            if (str_contains($ability, '.') && $user->hasPermission($ability)) {
                return true;
            }

            return null;
        });

        Paginator::defaultView('partials.pagination');

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('unreadNotifications', $user ? $user->unreadNotifications()->latest()->limit(8)->get() : collect());
            $view->with('unreadCount', $user ? $user->unreadNotifications()->count() : 0);
            $view->with('navCounts', $user ? \App\Support\NavCounts::for($user) : []);
        });
    }
}
