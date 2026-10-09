<?php

namespace App\Http\Controllers;

use App\Models\CustomerAcquisition;
use App\Models\Role;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard Omset Sales (Skema 2, 2026-09-12) — Business Development
 * memantau omset per Sales, per periode.
 *
 * Omset Sales = NILAI PPN 11% itu sendiri (bukan biaya dikurangi PPN) —
 * dikoreksi ulang user 2026-09-12 lewat contoh: Biaya Langganan Rp150.000
 * → Omset Rp16.500. BEDA dari `harga_dikurangi_ppn` (Rp133.500) yang
 * dipakai kolom /customer-acquisitions — dua metrik beda tujuan, lihat
 * CustomerAcquisition::getOmsetSalesAttribute(). JANGAN disatukan lagi.
 *
 * Klik nama Sales → modal breakdown per pelanggan (view-only, pola "3 pola
 * aksi" CLAUDE.md), datanya disiapkan di sini sekaligus (bukan endpoint AJAX
 * terpisah) karena volumenya kecil (satu bulan, satu POP scope).
 */
class SalesOmsetDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $periode = $request->query('periode', now()->format('Y-m'));
        // Filter "Role" & "Nama" (2026-09-12, permintaan user — pola sama
        // /customer-acquisitions) — KHUSUS role ber-`is_package_restricted`,
        // reuse `customers.sales_user_id`, bukan kolom baru.
        $roleId = $request->query('role_id');
        $salesUserId = $request->query('sales_user_id');

        $access = app(EffectiveAccessService::class);
        $user = $request->user();

        $records = CustomerAcquisition::query()
            ->with(['customer.customerService', 'customer.pop', 'customer.salesUser.role'])
            ->where('periode', $periode)
            ->whereHas('customer', function ($q) use ($access, $user, $roleId, $salesUserId) {
                $q->whereNotNull('sales_user_id');
                if (! $access->hasAllPopAccess($user)) {
                    $q->whereIn('pop_id', $access->getAllowedPopIds($user));
                }
                if ($roleId) {
                    $q->whereHas('salesUser', fn ($sq) => $sq->where('role_id', $roleId));
                }
                if ($salesUserId) {
                    $q->where('sales_user_id', $salesUserId);
                }
            })
            ->get();

        // Agregasi per sales — nama, role, jumlah pelanggan, total omset, breakdown.
        $bySales = $records->groupBy('customer.sales_user_id')->map(function ($group) {
            $salesUser = $group->first()->customer->salesUser;

            return [
                'sales_user_id' => $salesUser?->id,
                'sales_name' => $salesUser?->name ?? 'Sales Tidak Diketahui',
                'role_name' => $salesUser?->role?->name ?? '—',
                'jumlah_pelanggan' => $group->count(),
                // Tabel utama (permintaan user, 2026-09-12): total per
                // kolom, bukan cuma total omset — biar Busdev lihat
                // ringkasan biaya & PPN-nya juga tanpa buka rincian.
                'total_biaya_langganan' => $group->sum(fn ($record) => $record->customer->customerService?->total_monthly_bill),
                'total_harga_dikurangi_ppn' => $group->sum('harga_dikurangi_ppn'),
                'total_omset' => $group->sum('omset_sales'),
                'breakdown' => $group->map(function ($record) {
                    $customer = $record->customer;

                    return [
                        'nama' => $customer->full_name,
                        'tanggal_aktivasi' => $customer->customerService?->activation_date,
                        'pop' => $customer->pop?->name ?? '—',
                        'biaya_langganan' => $customer->customerService?->total_monthly_bill,
                        'harga_dikurangi_ppn' => $record->harga_dikurangi_ppn,
                        'omset' => $record->omset_sales,
                    ];
                })->values(),
            ];
        })->sortByDesc('total_omset')->values();

        $periodeOptions = CustomerAcquisition::query()
            ->selectRaw('DISTINCT periode')
            ->orderByDesc('periode')
            ->pluck('periode');

        if (! $periodeOptions->contains(now()->format('Y-m'))) {
            $periodeOptions = $periodeOptions->prepend(now()->format('Y-m'));
        }

        $restrictedRoles = Role::where('is_package_restricted', true)->orderBy('name')->get();

        $nameOptions = User::query()
            ->whereHas('role', function ($q) use ($roleId) {
                $q->where('is_package_restricted', true);
                if ($roleId) {
                    $q->where('id', $roleId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('business-development.sales-omset.index', [
            'bySales' => $bySales,
            'periode' => $periode,
            'periodeOptions' => $periodeOptions,
            'totalOmsetKeseluruhan' => $bySales->sum('total_omset'),
            'restrictedRoles' => $restrictedRoles,
            'nameOptions' => $nameOptions,
            'roleId' => $roleId,
            'salesUserId' => $salesUserId,
        ]);
    }
}
