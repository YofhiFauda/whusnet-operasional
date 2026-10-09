<?php

namespace App\Http\Controllers\BusinessDevelopment;

use App\Http\Controllers\Controller;
use App\Models\InternetPackage;
use App\Models\RestrictedPackage;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Restriksi Paket per Role (Skema 1, 2026-09-12) — Business Development
 * mengatur SATU daftar paket global yang boleh dipilih role ber-
 * `roles.is_package_restricted = true` (mis. Sales, Teknisi). Lihat
 * InternetPackage::scopeAvailableFor().
 *
 * Toggle role mana yang kena restriksi TETAP lewat halaman Role Management
 * yang sudah ada (`roles.update`) — controller ini murni mengelola isi
 * daftar paketnya.
 */
class PackageRestrictionController extends Controller
{
    public function index(): View
    {
        $packages = InternetPackage::active()->orderBy('name')->get();
        $selectedIds = RestrictedPackage::query()->pluck('package_id')->all();
        $restrictedRoles = Role::where('is_package_restricted', true)->orderBy('name')->get();

        return view('business-development.package-restrictions.index', compact('packages', 'selectedIds', 'restrictedRoles'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'package_ids' => 'nullable|array',
            'package_ids.*' => 'integer|exists:internet_packages,id',
        ]);

        $packageIds = $validated['package_ids'] ?? [];

        DB::transaction(function () use ($packageIds) {
            RestrictedPackage::query()->delete();
            foreach ($packageIds as $packageId) {
                RestrictedPackage::create(['package_id' => $packageId]);
            }
        });

        return redirect()
            ->route('business-development.package-restrictions.index')
            ->with('success', 'Daftar paket restriksi berhasil diperbarui.');
    }
}
