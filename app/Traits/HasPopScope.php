<?php

namespace App\Traits;

use App\Enums\ScopeType;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Database\Eloquent\Builder;

trait HasPopScope
{
    /**
     * Scope a query to only include records accessible by the user based on RBAC POP Scope.
     *
     * Tiga tipe scope yang berlaku:
     * - all_pop      → tidak ada filter, lihat semua data
     * - selected_pop → Cabang POP terpilih beserta Mini POP + semua distribusi di bawahnya
     * - pop_tree     → Distribusi POP spesifik yang dipilih dari cabang
     */
    public function scopeApplyUserScope(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            // Jika tidak ada user (misal guest), tolak semua data
            return $query->whereRaw('1 = 0');
        }

        // Owner & Atasan selalu punya akses penuh
        if ($user->hasRole('owner', 'atasan')) {
            return $query;
        }

        /** @var EffectiveAccessService $accessService */
        $accessService = app(EffectiveAccessService::class);
        $scopeType = $accessService->getScopeType($user);

        // Seluruh POP — tidak ada filter
        if ($scopeType === ScopeType::ALL_POP) {
            return $query;
        }

        // Cabang POP atau legacy pop_tree — filter berdasarkan daftar POP yang diizinkan
        if (in_array($scopeType, [ScopeType::SELECTED_POP, ScopeType::POP_TREE], true)) {
            $allowedPopIds = $accessService->getAllowedPopIds($user);

            return $query->where(function ($q) use ($allowedPopIds) {
                $q->whereIn('pop_id', $allowedPopIds);
            });
        }

        // Fallback: deny access jika scope tidak dikenali
        return $query->whereRaw('1 = 0');
    }
}
