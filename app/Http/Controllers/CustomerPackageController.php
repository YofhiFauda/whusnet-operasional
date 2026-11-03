<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InternetPackage;
use App\Services\CustomerPackageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerPackageController extends Controller
{
    /**
     * Pratinjau breakdown prorate (view-only, JSON) — dipanggil AJAX dari
     * modal/form "Ganti Paket" tiap kali admin pilih paket baru di dropdown,
     * supaya rincian "Prorate Paket Lama + Prorate Paket Baru"
     * (docs/plan/billing/upgrade-downgrade/Skema-downgrade-dan-upgrade.md)
     * kelihatan SEBELUM klik Simpan. Satu permission dengan `update()` —
     * cuma relevan buat user yang memang boleh ganti paket.
     */
    public function preview(Request $request, Customer $customer): JsonResponse
    {
        abort_unless(Customer::query()->applyUserScope()->whereKey($customer->id)->exists(), 403);

        $validated = $request->validate([
            'internet_package_id' => 'required|exists:internet_packages,id',
        ]);

        $newPackage = InternetPackage::findOrFail($validated['internet_package_id']);

        try {
            $preview = app(CustomerPackageService::class)->previewChange($customer, $newPackage);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($preview);
    }

    /**
     * Ganti paket internet pelanggan — inline toggle di tab "Paket &
     * Layanan" halaman Detail Pelanggan (bukan modal/halaman baru: aksi ini
     * lanjutan di halaman Detail miliknya sendiri, satu record, lihat
     * CLAUDE.md pola aksi #3). Redirect balik ke `customers.show` (PRG).
     */
    public function update(Request $request, Customer $customer): RedirectResponse
    {
        abort_unless(Customer::query()->applyUserScope()->whereKey($customer->id)->exists(), 403);

        $validated = $request->validate([
            'internet_package_id' => 'required|exists:internet_packages,id',
        ]);

        $newPackage = InternetPackage::findOrFail($validated['internet_package_id']);

        try {
            app(CustomerPackageService::class)->change($customer, $newPackage);
        } catch (\RuntimeException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', "Paket pelanggan {$customer->full_name} berhasil diganti ke {$newPackage->name}. Kalau tagihan periode ini sudah terbit, nominalnya sudah dihitung ulang prorata; kalau belum, harga baru berlaku penuh mulai tagihan periode berikutnya.");
    }
}
