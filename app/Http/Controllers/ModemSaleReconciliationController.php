<?php

namespace App\Http\Controllers;

use App\Models\Pop;
use App\Services\ModemSaleReconciliationService;
use App\Support\PerPage;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Laporan "SN Terjual Belum Ditagih" — rancangan 2026-10-31, `docs/plan/
 * warehouse/rancangan-modem-terjual-visibilitas-dan-rekonsiliasi.md` §5.
 *
 * Tipis: semua logic cocok/tidak-cocok + filter ada di
 * `ModemSaleReconciliationService`. Permission (`modem_sale_reconciliation.*`)
 * sudah membatasi siapa yang boleh akses halaman ini sama sekali (K1:
 * owner/atasan/admin) — POP scope tambahan ditegakkan di dalam service lewat
 * `EffectiveAccessService`, jadi admin Cabang (kalau suatu saat diberi izin
 * ini) tidak melihat pelanggan luar scope-nya.
 */
class ModemSaleReconciliationController extends Controller
{
    public function index(Request $request, ModemSaleReconciliationService $service): View
    {
        $filters = $this->filters($request);
        $rows = $service->rows($request->user(), $filters);

        // Status dihitung per baris di PHP (bukan kolom DB), jadi pagination
        // dipotong dari koleksi — ringkasan tetap dari SEMUA baris terfilter.
        $perPage = PerPage::resolve($request);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('reports.modem-sale-reconciliation.index', [
            'rows' => $paginated,
            'summary' => $service->summary($rows),
            'filters' => $filters,
            'statusOptions' => $service->statusOptions(),
            // Dropdown cuma Cabang — Pusat & Mini POP bukan satuan billing
            // (pola sama halaman Tagihan).
            'pops' => Pop::forUser()->where('type', 'cabang')->orderBy('name')->get(['id', 'name']),
            'canExport' => $request->user()->hasPermission('modem_sale_reconciliation.export'),
        ]);
    }

    public function export(Request $request, ModemSaleReconciliationService $service): BinaryFileResponse
    {
        $rows = $service->rows($request->user(), $this->filters($request));

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rekonsiliasi-modem-'.uniqid().'.xlsx';
        $writer = SimpleExcelWriter::create($path);

        // Teks berawalan = + - @ dinetralkan: OpenSpout menulis string
        // berawalan "=" sebagai FORMULA, dan nama barang/pelanggan berasal
        // dari input pengguna (formula injection).
        $safe = static fn (?string $value): string => preg_match('/^[=+\-@]/', $value ?? '') ? "'".$value : ($value ?? '');

        foreach ($rows as $row) {
            $sale = $row['sale'];
            $writer->addRow([
                'SN' => $safe($sale->inventorySerial?->serial_number),
                'Barang' => $safe($sale->inventorySerial?->item?->name),
                'Pelanggan' => $safe($sale->customer?->full_name),
                'CID' => $safe($sale->customer?->cid ?? $sale->customer?->customer_code),
                'Cabang' => $safe($sale->customer?->pop?->name),
                'Harga Jual' => (float) $sale->price,
                'Tanggal Jual' => $sale->created_at->format('Y-m-d H:i'),
                'Status' => $row['status_label'],
                'Keterangan' => $row['reason'] ?? '',
            ]);
        }

        $writer->close();

        return response()->download($path, 'rekonsiliasi-modem-terjual-'.now()->format('Ymd-His').'.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * @return array{search: string, pop_id: string, status: string, date_from: string, date_to: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search', '')),
            'pop_id' => (string) $request->query('pop_id', ''),
            'status' => (string) $request->query('status', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];
    }
}
