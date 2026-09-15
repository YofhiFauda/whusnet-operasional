<?php

namespace App\Http\Controllers;

use App\Models\CustomerAcquisition;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use App\Services\InstallationFeeInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Modul Customer Acquisition (dipakai tim Busdev) — "Pelanggan Aktif < 30
 * Hari Diverifikasi".
 *
 * Reset "tanggal 1" TIDAK pakai job/cron: query default selalu `periode`
 * bulan berjalan (tabel kosong lagi otomatis begitu bulan berganti, isinya
 * nambah sendiri lewat CustomerObserver saat ada pelanggan baru diverifikasi
 * admin). Bulan-bulan lalu tetap bisa dibuka lewat filter `periode` untuk
 * monitoring/arsip. "Harga Dikurangi PPN" dihitung live (lihat
 * CustomerAcquisition::getHargaDikurangiPpnAttribute()). Satu-satunya aksi
 * tulis: "Biaya Instalasi" (lihat updateInstallationFee()).
 */
class CustomerAcquisitionController extends Controller
{
    public function index(Request $request): View
    {
        $periode = $request->query('periode', now()->format('Y-m'));
        // Filter "siapa yang input" (2026-09-12, permintaan user) — role &
        // nama, KHUSUS role ber-`is_package_restricted` (itu yang disebut
        // user "role dengan pembatasan paket tadi"). Filter ini bekerja di
        // `customers.sales_user_id` — sengaja BUKAN kolom baru, reuse FK
        // Skema 3 yang sudah ada, jadi begitu Teknisi (atau role restricted
        // lain) diberi akses registrasi pelanggan, ikut kefilter otomatis
        // tanpa ubah kode.
        $roleId = $request->query('role_id');
        $salesUserId = $request->query('sales_user_id');

        $access = app(EffectiveAccessService::class);
        $user = $request->user();

        $records = CustomerAcquisition::query()
            ->with(['customer.customerService.internetPackage', 'customer.pop', 'customer.salesUser.role', 'installationFeeInvoice'])
            ->where('periode', $periode)
            // Wajib lewat POP scope (CLAUDE.md) — pop_admin/role ber-scope
            // cuma boleh lihat pelanggan di POP yang diizinkan.
            ->when(! $access->hasAllPopAccess($user), function ($query) use ($access, $user) {
                $query->whereHas('customer', function ($q) use ($access, $user) {
                    $q->whereIn('pop_id', $access->getAllowedPopIds($user));
                });
            })
            ->when($roleId, function ($query) use ($roleId) {
                $query->whereHas('customer.salesUser', function ($q) use ($roleId) {
                    $q->where('role_id', $roleId);
                });
            })
            ->when($salesUserId, function ($query) use ($salesUserId) {
                $query->whereHas('customer', function ($q) use ($salesUserId) {
                    $q->where('sales_user_id', $salesUserId);
                });
            })
            ->orderBy('verified_at')
            ->get();

        $periodeOptions = CustomerAcquisition::query()
            ->selectRaw('DISTINCT periode')
            ->orderByDesc('periode')
            ->pluck('periode');

        // Periode berjalan selalu tersedia di dropdown walau belum ada
        // barisnya sama sekali (bulan baru mulai kosong).
        if (! $periodeOptions->contains(now()->format('Y-m'))) {
            $periodeOptions = $periodeOptions->prepend(now()->format('Y-m'));
        }

        $restrictedRoles = Role::where('is_package_restricted', true)->orderBy('name')->get();

        // Daftar nama buat dropdown kedua — kalau role sudah dipilih,
        // dipersempit ke role itu saja (dropdown Nama ikut berubah).
        $nameOptions = User::query()
            ->whereHas('role', function ($q) use ($roleId) {
                $q->where('is_package_restricted', true);
                if ($roleId) {
                    $q->where('id', $roleId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('customer-acquisitions.index', [
            'records' => $records,
            'periode' => $periode,
            'periodeOptions' => $periodeOptions,
            'isCurrentPeriode' => $periode === now()->format('Y-m'),
            'restrictedRoles' => $restrictedRoles,
            'nameOptions' => $nameOptions,
            'roleId' => $roleId,
            'salesUserId' => $salesUserId,
        ]);
    }

    /**
     * Isi "Biaya Instalasi" — jalur FALLBACK. Sejak modul
     * `/business-development-verifications` ada, pelanggan kategori Bisnis
     * normalnya SUDAH kelar isi Biaya Instalasi di sana SEBELUM sempat
     * ACTIVE (baris `CustomerAcquisition` bahkan belum ada saat itu) — jadi
     * begitu baris ini muncul di sini, `installation_fee_invoice_id`
     * biasanya sudah terisi duluan. Endpoint ini tetap dipertahankan buat
     * kasus pinggir: data lama sebelum modul ini ada, atau status di-ubah
     * manual di luar alur normal.
     *
     * BUKAN sekadar catatan: begitu diisi, langsung menerbitkan tagihan
     * sungguhan (`Invoice` tipe INSIDENTAL, kategori Jasa Instalasi) lewat
     * `InstallationFeeInvoiceService` — SATU logika yang sama dipakai
     * `BusinessDevelopmentVerificationController::verify()`, jangan
     * diduplikasi lagi.
     *
     * SENGAJA invoice BARU & TERPISAH dari Invoice Awal (`extra_installation_fee`
     * yang diisi CS di `CustomerVerificationController::finalVerify()`) —
     * Invoice Awal lazimnya sudah dibayar di tempat saat aktivasi, mengubah
     * nominalnya belakangan berisiko ke rekonsiliasi pembayaran.
     *
     * Begitu invoice terbit, baris ini TERKUNCI (`installation_fee_invoice_id`
     * sudah terisi) — koreksi nominal lewat menu Tagihan biasa (batalkan,
     * lalu tagih ulang), bukan menimpa diam-diam dari sini.
     *
     * Gerbangnya DINAMIS per baris (`CustomerAcquisition::canBeValidatedBy()`,
     * dari role yang dipilih admin buat kategori paket pelanggan di Master
     * Kategori Paket), bukan permission statis — ganti pemetaan kategori→
     * role di Master Kategori Paket, TANPA ubah kode di sini.
     *
     * Kategori yang gak butuh validasi ini (`needsInstallationFeeValidation()`
     * false, mis. Home Broadband) menolak request — field itu tidak
     * relevan buat baris itu sama sekali, bukan cuma "boleh dikosongkan".
     */
    public function updateInstallationFee(Request $request, CustomerAcquisition $customerAcquisition, InstallationFeeInvoiceService $installationFeeInvoiceService): RedirectResponse
    {
        $customerAcquisition->loadMissing('customer.customerService.internetPackage');

        abort_unless($customerAcquisition->needsInstallationFeeValidation(), 404, 'Kategori paket pelanggan ini tidak butuh validasi Biaya Instalasi.');
        abort_unless($customerAcquisition->canBeValidatedBy($request->user()), 403, 'Anda tidak punya izin memvalidasi Biaya Instalasi kategori paket ini.');

        if ($customerAcquisition->installation_fee_invoice_id) {
            return redirect()
                ->route('customer-acquisitions.index', ['periode' => $customerAcquisition->periode])
                ->with('error', 'Biaya Instalasi pelanggan ini sudah diterbitkan jadi tagihan — tidak bisa diubah dari sini. Koreksi lewat menu Tagihan (batalkan, lalu terbitkan ulang) kalau nominalnya salah.');
        }

        $access = app(EffectiveAccessService::class);
        $user = $request->user();
        if (! $access->hasAllPopAccess($user) && ! in_array($customerAcquisition->customer->pop_id, $access->getAllowedPopIds($user), true)) {
            abort(403, 'Pelanggan ini di luar POP scope Anda.');
        }

        $validated = $request->validate([
            'installation_fee' => ['required', 'numeric', 'min:0.01'],
        ]);

        $invoice = $installationFeeInvoiceService->issue($customerAcquisition->customer, (float) $validated['installation_fee']);

        $customerAcquisition->update([
            'installation_fee' => $validated['installation_fee'],
            'installation_fee_invoice_id' => $invoice->id,
        ]);

        return redirect()
            ->route('customer-acquisitions.index', ['periode' => $customerAcquisition->periode])
            ->with('success', "Biaya Instalasi berhasil diterbitkan sebagai tagihan {$invoice->invoice_number}.");
    }
}
