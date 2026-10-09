<?php

namespace App\Http\Controllers;

use App\Enums\BillingWaiverSource;
use App\Models\Customer;
use App\Models\CustomerBillingWaiver;
use App\Services\BillingPeriodWaiverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Cuti Berlangganan" (ADHOC-87) — pintu KEDUA ke `BillingPeriodWaiverService`
 * (pintu pertama: Request Putus Langganan, lihat `CustomerTerminationController`).
 * Beda dari terminasi: status pelanggan TIDAK berubah, cuma tagihan periode
 * terpilih yang dibebaskan. Inline toggle di Detail Pelanggan (pola-3
 * CLAUDE.md), bukan modal/halaman baru.
 */
class CustomerBillingWaiverController extends Controller
{
    /**
     * Cuti Berlangganan — bebaskan satu/beberapa periode (sudah terbit
     * maupun belum) tanpa mengubah status pelanggan.
     */
    public function store(Request $request, Customer $customer, BillingPeriodWaiverService $service): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('billing_waivers.create'), 403);
        $this->authorizeScope($customer);

        $validated = $request->validate([
            'periods' => 'required|array|min:1',
            'periods.*' => ['string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'reason' => 'required|string|max:1000',
        ]);

        $service->waive(
            $customer,
            $validated['periods'],
            $validated['reason'],
            BillingWaiverSource::LEAVE,
            auth()->user(),
        );

        return redirect()->back()->with('success', count($validated['periods']).' periode tagihan berhasil dibebaskan (Cuti Berlangganan).');
    }

    /**
     * Cabut pembebasan — invoice yang sudah `batal` TIDAK dihidupkan lagi
     * (K6 rancangan); kalau perlu ditagih ulang, jalankan
     * `billing:generate-monthly-invoices --period=…` setelah ini.
     */
    public function destroy(Request $request, CustomerBillingWaiver $waiver, BillingPeriodWaiverService $service): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('billing_waivers.delete'), 403);
        $this->authorizeScope($waiver->customer);

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $service->revoke($waiver, auth()->user(), $validated['reason']);

        return redirect()->back()->with('success', 'Pembebasan tagihan periode '.$waiver->billing_period.' berhasil dicabut.');
    }

    private function authorizeScope(Customer $customer): void
    {
        abort_unless(
            Customer::query()->applyUserScope()->whereKey($customer->id)->exists(),
            403,
            'Anda tidak memiliki akses ke pelanggan di POP ini.'
        );
    }
}
