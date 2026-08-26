<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Task;
use App\Observers\CustomerObserver;
use App\Observers\FopTaskObserver;
use App\Observers\InvoiceObserver;
use App\Observers\PaymentObserver;
use App\Observers\TaskObserver;
use App\Policies\TaskPolicy;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // Akses UI dokumentasi Scramble (/docs/api) di luar env local — bebas
        // di local, tapi di production/staging cuma staf full-access
        // (Owner/Admin) yang boleh buka. Bukan gerbang permission granular
        // (RBAC matrix) karena ini dokumentasi teknis, bukan fitur bisnis —
        // tanpa Gate ini RestrictedDocsAccess default-nya 403 SEMUA orang di
        // luar local (dedoc/scramble), dokumen jadi gak bisa diakses staf pun.
        Gate::define('viewApiDocs', fn ($user) => $user->hasFullAccess());

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

        // Skema HTTPS untuk URL yang dibuat DI LUAR konteks request — queue job,
        // artisan command, notifikasi, tautan di email/Telegram. Di dalam request,
        // skema sudah benar sendiri lewat trusted proxies (bootstrap/app.php) yang
        // membaca X-Forwarded-Proto dari nginx / Cloudflare Tunnel, jadi JANGAN
        // tambahkan lagi deteksi header di sini: versi lama memaksa https untuk
        // SEMUA request begitu APP_ENV=production — termasuk akses langsung
        // http://ip:8000 tanpa tunnel, yang lalu menghasilkan tautan https ke port
        // yang tidak melayani TLS.
        //
        // Dipakai bareng APP_URL berskema https. Nilainya dibaca dari config,
        // bukan env() langsung, supaya tetap benar setelah `config:cache`.
        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        // Ability berbentuk kode permission (`feature.action`, SELALU ber-titik
        // — lihat CLAUDE.md § RBAC) didelegasikan langsung ke
        // User::hasPermission(). Dulu di sini ada loop `Permission::all()` yang
        // bikin Gate::define() satu-satu per baris permission SEKALI saat app
        // boot — dua masalah nyata:
        // 1. Permission yang baru di-seed SETELAH boot (mis. lewat
        //    `$this->seed()` di tengah test, app cuma di-boot sekali di awal
        //    test) gak pernah punya Gate ability terdaftar → `@can(...)`
        //    selalu false walau `hasPermission()` langsung sudah true persis
        //    saat itu. Di request produksi beneran gak kelihatan (tiap
        //    request = boot baru = query ulang), tapi bikin test flaky/salah
        //    tanpa ada bug produksi.
        // 2. Query `Permission::all()` di SETIAP boot, padahal gak pernah
        //    dipakai buat apa pun selain daftar nama ability.
        //
        // GERBANG WAJIB: cuma ability yang mengandung '.' yang diperiksa di
        // sini. Ability Policy (mis. `TaskPolicy::cancel`/`cancelViaFopTask`,
        // dipanggil `Gate::allows('cancel', $task)`) SELALU kata tunggal tanpa
        // titik — kalau ability apa pun diperiksa di sini, `Gate::before()`
        // global ini short-circuit SEBELUM `TaskPolicy::before()` dapat giliran,
        // dan wildcard '*' owner lolos nembus invarian SRV/PSB yang sengaja
        // dikecualikan Policy-nya (regresi nyata, ketauan dari
        // `FopTaskCancelCascadeAuthTest::test_owner_wildcard_cannot_bypass_survey_invariant`).
        // Ability berbasis `permission->name` (label manusia) TIDAK pernah
        // dipakai di satu pun `@can()`/`can()` di codebase ini (diverifikasi
        // grep) — sengaja dilepas, bukan kelalaian.
        Gate::before(function ($user, string $ability) {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });

        // API Baru — Topologi Jaringan & Konfirmasi Assignment
        // (docs/api/api-pop-distribusi/, rencana-implementasi.md §"Keputusan
        // resmi" #4). Endpoint #1 baca-saja, referensi jarang berubah →
        // limiter longgar per token. Endpoint #2 nulis identitas pelanggan →
        // jauh lebih ketat, di-key per token+IP (bukan token doang) supaya
        // satu integrator yang IP-nya berganti-ganti tidak berbagi kuota
        // dengan pemanggil lain yang kebetulan pegang token sama.
        RateLimiter::for('pop-distribusi-read', function (Request $request) {
            return Limit::perMinute(120)->by($request->bearerToken() ?? $request->ip());
        });

        RateLimiter::for('network-assignment-write', function (Request $request) {
            return Limit::perMinute(20)->by(($request->bearerToken() ?? 'anon').'|'.$request->ip());
        });

        // Limiter TERPISAH dari network-assignment-write meski dua endpoint
        // berbagi token tulis yang sama — dua bucket independen supaya
        // rentetan gagal di satu endpoint gak ikut ngabisin kuota endpoint
        // yang lain (keputusan.md §19).
        RateLimiter::for('network-device-write', function (Request $request) {
            return Limit::perMinute(20)->by(($request->bearerToken() ?? 'anon').'|'.$request->ip());
        });

        // API Portal Pelanggan (docs/api/api-portal-pelanggan/, Fase 0). Tiga
        // limiter beda kelas risiko — pola RateLimiter::for-nya sama seperti
        // tiga di atas, bukan pola baru di repo.
        RateLimiter::for('customer-portal-api', function (Request $request) {
            return Limit::perMinute(120)->by($request->bearerToken() ?? $request->ip());
        });

        // Jauh lebih ketat dari customer-portal-api — endpoint kredensial
        // (login/claim/ganti password, Fase 2) TIDAK BOLEH pakai limiter data
        // biasa: 120 req/menit di situ setara brute-force yang diizinkan
        // (business-logic.md §6.6.3). Belum diattach ke route nyata sampai
        // Fase 2 — didaftarkan sekarang supaya namanya siap dipakai.
        RateLimiter::for('customer-portal-auth', function (Request $request) {
            return Limit::perMinutes(15, 5)->by($request->ip().'|'.$request->input('login_id'));
        });

        // Limiter KEDUA, murni per-IP tanpa login_id — menutup penyapuan
        // banyak login_id dari satu IP yang tidak pernah menyentuh limiter
        // di atas (keputusan.md §1: limiter keyed login_id+IP saja ditolak,
        // "memberi ember baru untuk tiap login ID").
        RateLimiter::for('customer-portal-auth-ip', function (Request $request) {
            return Limit::perMinutes(15, 30)->by($request->ip());
        });

        // View Composer for Sidebar Badges
        View::composer('layouts.app', function ($view) {
            $surveyCount = Customer::whereIn('status', ['waiting_survey', 'survey_in_progress'])->count();
            $verificationCount = Customer::whereIn('status', ['surveyed', 'waiting_acc', 'waiting_installation', 'installation_in_progress', 'installed', 'verification_admin'])->count();

            $view->with('badge_survey_count', $surveyCount)
                ->with('badge_verification_count', $verificationCount);
        });
    }
}
