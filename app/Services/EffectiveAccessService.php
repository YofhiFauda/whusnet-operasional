<?php

namespace App\Services;

use App\Enums\ScopeType;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class EffectiveAccessService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get the effective role for the user.
     */
    public function getRole(User $user)
    {
        return $user->role;
    }

    /**
     * Get all permission codes for the user.
     */
    public function getPermissions(User $user): array
    {
        return Cache::remember("user.{$user->id}.permissions", self::CACHE_TTL, function () use ($user) {
            $role = $this->getRole($user);
            if (! $role) {
                return [];
            }

            if ($role->code === 'owner') {
                return ['*'];
            }

            return $role->permissions()->pluck('code')->toArray();
        });
    }

    /**
     * Check if user has a specific permission.
     */
    public function userCan(User $user, string $featureCode, ?string $actionCode = null): bool
    {
        $permissions = $this->getPermissions($user);
        $code = $actionCode ? "{$featureCode}.{$actionCode}" : $featureCode;

        // 1. Exact match
        if (in_array($code, $permissions)) {
            return true;
        }

        // 2. Global wildcard match (*)
        if (in_array('*', $permissions)) {
            return true;
        }

        // 3. Feature wildcard match (e.g., customers.* or nested customers.import.*)
        $parts = explode('.', $code);
        if (count($parts) > 1) {
            $featureWildcard = $parts[0].'.*';
            if (in_array($featureWildcard, $permissions)) {
                return true;
            }

            // Nested feature wildcard match (e.g., checking customers.import, user has customers.import.*)
            $nestedWildcard = $code.'.*';
            if (in_array($nestedWildcard, $permissions)) {
                return true;
            }
        } else {
            // Single part code (e.g., checking pops, user has pops.*)
            $featureWildcard = $code.'.*';
            if (in_array($featureWildcard, $permissions)) {
                return true;
            }
        }

        // 4. Prefix match for features (e.g., checking customers.import, user has customers.import.view)
        foreach ($permissions as $permission) {
            if (str_starts_with($permission, $code.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * SIAPA SAJA yang punya permission ini — kebalikan arah `userCan()`.
     *
     * Dipakai untuk memilih audiens: siapa yang perlu dikabari, siapa yang
     * boleh muncul di daftar pilihan. Sebelum ini tiap pemanggil menuliskan
     * daftar role-nya sendiri (`whereIn('code', ['owner', 'atasan'])`), dan
     * daftar seperti itu langsung salah begitu Role Matrix memberikan
     * permission yang sama ke role lain: orangnya berwenang, tapi tak pernah
     * dikabari — kewenangan yang tak terlihat sama saja dengan tak ada.
     *
     * Pencocokannya mengikuti `userCan()`: exact, wildcard global `*`, feature
     * wildcard, nested wildcard, dan prefix. Role `owner` selalu lolos karena
     * `getPermissions()` memberinya `*` tanpa baris apa pun di
     * `role_permissions` — kalau tidak diperlakukan khusus di sini, Owner
     * justru jadi satu-satunya yang tak pernah masuk audiens.
     *
     * @return Builder<User>
     */
    public function usersWithPermission(string $code): Builder
    {
        $parts = explode('.', $code);

        $wildcards = ['*', $code.'.*'];
        if (count($parts) > 1) {
            $wildcards[] = $parts[0].'.*';
        }

        return User::query()->whereHas('role', function ($role) use ($code, $wildcards) {
            $role->where('code', 'owner')
                ->orWhereHas('permissions', function ($permission) use ($code, $wildcards) {
                    $permission->where('code', $code)
                        ->orWhereIn('code', $wildcards)
                        ->orWhere('code', 'like', $code.'.%');
                });
        });
    }

    /**
     * Apakah user punya akses ke SEMUA POP (bukan sekadar allowedPopIds kosong).
     * Beda dari "empty(getAllowedPopIds())" — itu ambigu antara ALL_POP asli
     * vs user yang scope-nya belum di-setup sama sekali (harus di-treat sebagai
     * deny-by-default, bukan dikasih akses penuh).
     */
    public function hasAllPopAccess(User $user): bool
    {
        if ($user->hasRole('owner', 'atasan')) {
            return true;
        }

        return $this->getScopeType($user) === ScopeType::ALL_POP;
    }

    /**
     * Get the scope type for the user.
     */
    public function getScopeType(User $user): ?ScopeType
    {
        return Cache::remember("user.{$user->id}.scope_type", self::CACHE_TTL, function () use ($user) {
            $scope = $user->roleScopes()->first();

            return $scope ? $scope->scope_type : null;
        });
    }

    /**
     * Get allowed POP IDs for the user based on their scope type.
     * Returns empty array for ALL_POP (handled separately — no filter needed).
     */
    public function getAllowedPopIds(User $user): array
    {
        return Cache::remember("user.{$user->id}.allowed_pop_ids", self::CACHE_TTL, function () use ($user) {
            $scopeType = $this->getScopeType($user);
            if (! $scopeType) {
                return [];
            }

            $scope = $user->roleScopes()->with('targets')->first();

            switch ($scopeType) {
                case ScopeType::ALL_POP:
                    // Tidak perlu daftar ID — query tidak di-filter
                    return [];

                case ScopeType::SELECTED_POP:
                    // Cabang POP: data dari cabang yang dipilih + sub-POP di bawahnya (via pop_tree resolver)
                    $basePopIds = $scope->targets->pluck('pop_id')->toArray();

                    return $this->resolvePopTree($basePopIds);

                case ScopeType::POP_TREE:
                    // Legacy support untuk data lama yang masih pakai pop_tree
                    $basePopIds = $scope->targets->pluck('pop_id')->toArray();

                    return $this->resolvePopTree($basePopIds);
            }

            return [];
        });
    }

    /**
     * Resolve the POP tree (base POPs + all their descendants).
     */
    private function resolvePopTree(array $basePopIds): array
    {
        $resolvedIds = [];
        $toProcess = $basePopIds;

        while (! empty($toProcess)) {
            $currentId = array_shift($toProcess);
            if (! in_array($currentId, $resolvedIds)) {
                $resolvedIds[] = $currentId;

                $childrenIds = Pop::where('parent_id', $currentId)->pluck('id')->toArray();
                $toProcess = array_merge($toProcess, $childrenIds);
            }
        }

        return $resolvedIds;
    }

    /**
     * Clear access cache for a user.
     */
    public function clearCache(User $user): void
    {
        Cache::forget("user.{$user->id}.role");
        Cache::forget("user.{$user->id}.permissions");
        Cache::forget("user.{$user->id}.scope_type");
        Cache::forget("user.{$user->id}.allowed_pop_ids");
    }
}
