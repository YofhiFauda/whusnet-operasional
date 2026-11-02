<?php

namespace App\Http\Controllers;

use App\Services\ModemSaleReconciliationService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan "SN Terjual Belum Ditagih" — rancangan 2026-10-31, `docs/plan/
 * warehouse/rancangan-modem-terjual-visibilitas-dan-rekonsiliasi.md` §5.
 *
 * Tipis: semua logic cocok/tidak-cocok ada di `ModemSaleReconciliationService`.
 * Permission (`modem_sale_reconciliation.*`) sudah membatasi siapa yang
 * boleh akses halaman ini sama sekali (K1: owner/atasan/admin) — POP scope
 * tambahan ditegakkan di dalam service lewat `EffectiveAccessService`,
 * jadi admin Cabang (kalau suatu saat diberi izin ini) tidak melihat
 * pelanggan luar scope-nya.
 */
class ModemSaleReconciliationController extends Controller
{
    public function index(Request $request, ModemSaleReconciliationService $service): View
    {
        $rows = $service->rows($request->user());
        $summary = $service->summary($rows);

        return view('reports.modem-sale-reconciliation.index', [
            'rows' => $rows,
            'summary' => $summary,
            'canExport' => $request->user()->hasPermission('modem_sale_reconciliation.export'),
        ]);
    }

    public function export(Request $request, ModemSaleReconciliationService $service): StreamedResponse
    {
        $rows = $service->rows($request->user());

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['SN', 'Barang', 'Pelanggan', 'Cabang', 'Harga Jual', 'Tanggal Jual', 'Status']);
            // Awalan = + - @ dinetralkan: nama barang/pelanggan berasal dari input pengguna.
            $safe = static fn (?string $value): string => preg_match('/^[=+\-@]/', $value ?? '') ? "'".$value : ($value ?? '');

            foreach ($rows as $row) {
                $sale = $row['sale'];
                fputcsv($out, [
                    $safe($sale->inventorySerial?->serial_number),
                    $safe($sale->inventorySerial?->item?->name),
                    $safe($sale->customer?->full_name),
                    $safe($sale->customer?->pop?->name),
                    number_format((float) $sale->price, 2, '.', ''),
                    $sale->created_at->format('Y-m-d H:i'),
                    $row['status_label'],
                ]);
            }

            fclose($out);
        }, 'rekonsiliasi-modem-terjual-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
