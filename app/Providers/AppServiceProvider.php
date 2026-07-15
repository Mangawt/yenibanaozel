<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class);
        Gate::define('admin.view', fn ($user): bool => $user->can('viewAdmin', $user));
        Gate::define('admin.write', fn ($user): bool => $user->can('writeAdmin', $user));
        Gate::define('admin.manage_users', fn ($user): bool => $user->can('manageUsers', $user));

        View::composer('*', function ($view): void {
            if (! array_key_exists('settings', $view->getData())) {
                $view->with('settings', app(\App\Services\Settings::class)->allPublic());
            }
        });

        if (! app()->isProduction()) {
            return;
        }

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceRootUrl((string) config('app.url'));
            URL::forceScheme('https');
        }
    }
}
