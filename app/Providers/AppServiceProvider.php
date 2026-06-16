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
        if (file_exists(app_path('Helpers/helpers.php'))) {
            require_once app_path('Helpers/helpers.php');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('id');

        // Register Blade Directives for formatting
        \Illuminate\Support\Facades\Blade::directive('rupiah', function ($expression) {
            return "<?php echo \App\Helpers\FormatHelper::rupiah($expression); ?>";
        });

        \Illuminate\Support\Facades\Blade::directive('tanggal', function ($expression) {
            return "<?php echo \App\Helpers\FormatHelper::tanggal($expression); ?>";
        });

        \Illuminate\Support\Facades\Blade::directive('jam', function ($expression) {
            return "<?php echo \App\Helpers\FormatHelper::jam($expression); ?>";
        });

        \Illuminate\Support\Facades\Blade::directive('datetime', function ($expression) {
            return "<?php echo \App\Helpers\FormatHelper::datetime($expression); ?>";
        });

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
