@extends('layouts.app')

@section('title', 'Rekonsiliasi Modem Terjual')
@section('page_title', 'Rekonsiliasi Modem Terjual')

@section('content')
<div class="space-y-5">
    <div>
        <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">SN Terjual Belum Ditagih</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Setiap SN "Beli dari Gudang" wajib berujung ke satu baris tagihan. Baris di bawah ini belum punya penaut tagihan sama sekali — sebagian wajar (masih menunggu langkah selanjutnya), sebagian butuh ditindaklanjuti.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-xs uppercase tracking-wide text-slate-500">Total baris belum tertagih</p>
            <p class="text-2xl font-bold tabular-nums">{{ $summary['total'] }}</p>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 dark:border-rose-900 dark:bg-slate-900">
            <p class="text-xs uppercase tracking-wide text-rose-700 dark:text-rose-400">Anomali (perlu ditindaklanjuti)</p>
            <p class="text-2xl font-bold tabular-nums text-rose-700 dark:text-rose-400">{{ $summary['anomaly'] }}</p>
        </div>
    </div>

    @if($canExport)
        <a href="{{ route('reports.modem-sale-reconciliation.export') }}" hx-boost="false" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Ekspor CSV</a>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-3">SN</th>
                    <th class="px-4 py-3">Barang</th>
                    <th class="px-4 py-3">Pelanggan</th>
                    <th class="px-4 py-3">Cabang</th>
                    <th class="px-4 py-3 text-right">Harga Jual</th>
                    <th class="px-4 py-3">Tanggal Jual</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($rows as $row)
                    @php $sale = $row['sale']; @endphp
                    <tr>
                        <td class="px-4 py-3 font-mono">{{ $sale->inventorySerial?->serial_number ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $sale->inventorySerial?->item?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $sale->customer?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $sale->customer?->pop?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">Rp{{ number_format((float) $sale->price, 2, ',', '.') }}</td>
                        <td class="px-4 py-3">{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            @if($row['status'] === \App\Services\ModemSaleReconciliationService::STATUS_ANOMALY)
                                <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-950/50 dark:text-rose-400">{{ $row['status_label'] }}</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">{{ $row['status_label'] }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">Tidak ada SN terjual yang belum tertagih.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
