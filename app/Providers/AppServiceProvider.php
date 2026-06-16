<?php

namespace App\Providers;

use Carbon\Carbon;
use App\Models\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Carbon::setLocale('id');

        // Force HTTPS jika diakses via proxy (seperti ngrok)
        if (request()->server('HTTP_X_FORWARDED_PROTO') == 'https' || app()->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Register Gates from permissions
        try {
            if (app()->runningInConsole() === false || app()->runningUnitTests()) {
                $permissions = Permission::all();
                foreach ($permissions as $permission) {
                    Gate::define($permission->name, function ($user) use ($permission) {
                        return $user->hasPermission($permission->name);
                    });
                }
            }
        } catch (\Exception $e) {
            // Skip if table doesn't exist yet
        }
    }
}
