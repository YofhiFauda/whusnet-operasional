<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\InternetPackage;
use App\Models\PackageCategory;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master Kategori Paket — CRUD penuh.
 *
 * `quickStore()` — modal cepat "Tambah Kategori" dipanggil dari form Master
 * Paket (create/edit), TIDAK berubah dari desain lama (lihat dokblok di
 * bawah), tetap dipertahankan buat alur cepat itu.
 *
 * `index()`/`create()`/`store()`/`edit()`/`update()`/`destroy()` — halaman
 * tersendiri, tempat admin ATUR SEMUA kategori (bukan cuma nambah cepat).
 *
 * `installation_fee_approval_role_id` DIPILIH DARI DAFTAR ROLE (nama biasa
 * seperti "Business Development", bukan kode permission mentah macam
 * "customer_acquisitions.installation_fee.update") — dikoreksi user
 * eksplisit: dropdown ratusan permission terlalu teknis buat admin
 * non-developer. Jalur teknis (permission via Role Matrix) tetap ada di
 * `CustomerAcquisition::canBeValidatedBy()` buat admin/owner.
 */
class PackageCategoryController extends Controller
{
    public function index(): View
    {
        $categories = PackageCategory::with('installationFeeApprovalRole')
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('master.package_categories.index', compact('categories'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->get();

        return view('master.package_categories.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateForm($request);

        $category = PackageCategory::create($validated);

        return redirect()
            ->route('master.package-categories.index')
            ->with('success', "Kategori \"{$category->name}\" berhasil ditambahkan.");
    }

    public function edit(PackageCategory $packageCategory): View
    {
        $roles = Role::orderBy('name')->get();

        return view('master.package_categories.edit', compact('packageCategory', 'roles'));
    }

    public function update(Request $request, PackageCategory $packageCategory): RedirectResponse
    {
        $validated = $this->validateForm($request, $packageCategory);

        $packageCategory->update($validated);

        return redirect()
            ->route('master.package-categories.index')
            ->with('success', "Kategori \"{$packageCategory->name}\" berhasil diperbarui.");
    }

    /**
     * Hard delete HANYA kalau belum ada paket (`internet_packages.category`
     * — string, bukan FK) yang memakai nama kategori ini. Kalau sudah
     * dipakai, tolak & arahkan nonaktifkan saja (`is_active`) — pola sama
     * seperti master lain di repo ini (revenue_categories dkk): kategori
     * yang sedang dirujuk tidak boleh hilang, nanti paket lama "kehilangan"
     * kategorinya di filter/laporan.
     */
    public function destroy(PackageCategory $packageCategory): RedirectResponse
    {
        $dipakai = InternetPackage::where('category', $packageCategory->name)->exists();

        if ($dipakai) {
            return redirect()
                ->route('master.package-categories.index')
                ->with('error', "Kategori \"{$packageCategory->name}\" masih dipakai paket internet yang ada — nonaktifkan saja (bukan hapus), lewat tombol Edit.");
        }

        $packageCategory->delete();

        return redirect()
            ->route('master.package-categories.index')
            ->with('success', "Kategori \"{$packageCategory->name}\" berhasil dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateForm(Request $request, ?PackageCategory $current = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('package_categories', 'name')->ignore($current?->id),
            ],
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'installation_fee_approval_role_id' => ['nullable', Rule::exists('roles', 'id')],
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['installation_fee_approval_role_id'] = $validated['installation_fee_approval_role_id'] ?: null;

        return $validated;
    }

    /**
     * Master Kategori Paket — cuma `store`, dipanggil dari modal "Tambah
     * Kategori" di form Master Paket (create & edit), bukan halaman tersendiri.
     *
     * Sengaja bukan halaman create/list terpisah (beda dengan `ItemCategory`/
     * `RevenueCategory`): kategori paket cuma butuh nama, dan yang butuh
     * ditambah cepat justru admin lagi di tengah mengisi form paket. Endpoint
     * ini dipanggil lewat fetch JSON dari modal, BUKAN submit form biasa —
     * jadi gagal validasi tidak kena masalah `back()->withErrors()` balik ke
     * referer yang melenyapkan isian form paket yang sedang diketik.
     */
    public function quickStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('package_categories', 'name'),
            ],
        ]);

        $category = PackageCategory::create($validated + ['is_active' => true]);

        return response()->json([
            'id' => $category->id,
            'name' => $category->name,
        ], 201);
    }
}
