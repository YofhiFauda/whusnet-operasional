<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Task;
use App\Observers\CustomerObserver;
use App\Observers\FopTaskObserver;
use App\Observers\InvoiceObserver;
use App\Observers\PaymentObserver;
use App\Observers\TaskObserver;
use App\Policies\TaskPolicy;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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

        // Detektor N+1. Sengaja TIDAK aktif di produksi: lazy load yang lolos ke
        // produksi harus jadi query lambat, bukan halaman 500 di depan pelanggan.
        // Di dev & test dia melempar LazyLoadingViolationException supaya setiap
        // relasi yang lupa di-eager-load ketahuan saat itu juga — tanpa ini,
        // N+1 menumpuk diam-diam (lihat FopDashboardController::getTeknisiList
        // yang sempat 5 query per teknisi tanpa satu pun test yang merah).
        Model::preventLazyLoading(! app()->isProduction());

        // Register Policies
        Gate::policy(Task::class, TaskPolicy::class);

        // Centralized invoice/payment guards — applies to every insert path
        // (controllers, artisan commands, future API), not just one controller.
        Invoice::observe(InvoiceObserver::class);
        Payment::observe(PaymentObserver::class);

        // Task 9 — sync status Task eksekusi teknisi ke FopTask (status realtime).
        Task::observe(TaskObserver::class);

        // Broadcast realtime antrean verifikasi — nutup dua admin yang bisa
        // verifikasi pelanggan sama tanpa saling tahu (docs/plan/analisa-
        // realtime-spa-operasional.md §2.1 no. 10).
        Customer::observe(CustomerObserver::class);

        // Fase 5.2 — isi kolom nyata notifications.notification_type dari data['type']
        // saat notifikasi dibuat, dari SEMUA jalur (Notification::send, dsb),
        // supaya filter halaman notifikasi jadi lookup ter-index, bukan
        // where('data->type') yang full-scan + parse JSON per baris. Notifikasi
        // immutable setelah dibuat, jadi nilai ini tak pernah drift.
        DatabaseNotification::creating(function ($notification) {
            $data = $notification->data;
            if (is_string($data)) {
                $data = json_decode($data, true) ?: [];
            }
            $notification->notification_type = is_array($data) ? ($data['type'] ?? null) : null;
        });

        // Ticketing — tulis riwayat sisi Ticket saat Task FOP-nya dibatalkan,
        // dari jalur cancel mana pun.
        FopTask::observe(FopTaskObserver::class);

        // Register Blade Directives for formatting
        Blade::directive('rupiah', function ($expression) {
            return "<?php echo \App\Helpers\FormatHelper::rupiah($expression); ?>";
        });

        Blade::directive('tanggal', function ($expression) {
            return "<?php echo \App\Helpers\FormatHelper::tanggal($expression); ?>";
        });

        Blade::directive('jam', function ($expression) {
            return "<?php echo \App\Helpers\FormatHelper::jam($expression); ?>";
        });

        Blade::directive('datetime', function ($expression) {
            return "<?php echo \App\Helpers\FormatHelper::datetime($expression); ?>";
        });

        // Force HTTPS jika diakses via proxy (seperti ngrok)
        if (request()->server('HTTP_X_FORWARDED_PROTO') == 'https' || app()->environment('production')) {
            URL::forceScheme('https');
        }

        // Register Gates from permissions
        try {
            if (app()->runningInConsole() === false || app()->runningUnitTests()) {
                $permissions = Permission::all();
                foreach ($permissions as $permission) {
                    if ($permission->code) {
                        Gate::define($permission->code, function ($user) use ($permission) {
                            return $user->hasPermission($permission->code);
                        });
                    }
                    if ($permission->name) {
                        Gate::define($permission->name, function ($user) use ($permission) {
                            return $user->hasPermission($permission->name);
                        });
                    }
                }
            }
        } catch (\Exception $e) {
            // Skip if table doesn't exist yet
        }

        // View Composer for Sidebar Badges
        View::composer('layouts.app', function ($view) {
            $surveyCount = Customer::whereIn('status', ['waiting_survey', 'survey_in_progress'])->count();
            $verificationCount = Customer::whereIn('status', ['surveyed', 'waiting_acc', 'waiting_installation', 'installation_in_progress', 'installed', 'verification_admin'])->count();

            $view->with('badge_survey_count', $surveyCount)
                ->with('badge_verification_count', $verificationCount);
        });
    }
}
