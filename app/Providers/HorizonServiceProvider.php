<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     *
     * Khusus role `owner` (keputusan user 2026-10-27), bukan `hasFullAccess()`:
     * dashboard ini memuat payload job (data pelanggan/pembayaran) dan bisa
     * retry/hapus job gagal, jadi tidak boleh ikut terbuka kalau suatu hari ada
     * role lain yang diberi wildcard `*`. Tanpa daftar email hardcode —
     * ganti pemilik cukup lewat role, tanpa deploy ulang.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => $user?->hasRole('owner') ?? false);
    }

    /**
     * Bawaan Horizon membuka dashboard untuk SIAPA PUN selama env `local`
     * (`|| app()->environment('local')`). Ditimpa supaya gate owner-only
     * berlaku di semua env, sama dengan /log-viewer — kalau env server salah
     * set ke `local`, dashboard tidak ikut terbuka untuk publik.
     */
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(fn ($request): bool => Gate::check('viewHorizon', [$request->user()]));
    }
}
