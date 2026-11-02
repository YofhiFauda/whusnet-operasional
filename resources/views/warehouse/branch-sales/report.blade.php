@extends('layouts.app')

@section('title', 'Laporan Jual Beli per SN')
@section('page_title', 'Laporan Jual Beli per SN')

@section('content')
<x-warehouse.header active="branch-sales" title="Laporan Jual Beli per SN" subtitle="Rincian tiap unit: Cabang penjual dan pembeli, HPP, harga jual, serta status fisik." />

<div class="space-y-5">
    <a href="{{ route('warehouse.branch-sales.index') }}" class="text-sm font-semibold text-sky-700 hover:underline">← Daftar jual beli</a>

    <form method="GET" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-2 lg:grid-cols-4">
        <select name="from_pop_id" aria-label="Cabang penjual" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">Semua penjual</option>
            @foreach($pops as $pop)<option value="{{ $pop->id }}" @selected(request('from_pop_id') == $pop->id)>{{ $pop->name }}</option>@endforeach
        </select>
        <select name="to_pop_id" aria-label="Cabang pembeli" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">Semua pembeli</option>
            @foreach($pops as $pop)<option value="{{ $pop->id }}" @selected(request('to_pop_id') == $pop->id)>{{ $pop->name }}</option>@endforeach
        </select>
        <select name="status" aria-label="Status fisik" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">Semua status</option>
            @foreach($statusLabels as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
        </select>
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nomor SN" aria-label="Cari nomor SN" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
        <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Tanggal mulai" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
        <input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Tanggal akhir" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
        <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Terapkan filter</button>
        <a href="{{ route('warehouse.branch-sales.report.export', request()->query()) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Ekspor CSV</a>
    </form>

    @if($summary !== null)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-slate-700 dark:border-sky-900 dark:bg-slate-900 dark:text-slate-200">
            <h2 class="font-semibold">Acuan cross-check pembayaran internal</h2>
            <p class="mt-1">Hanya SN berstatus <strong>Diterima</strong> yang dihitung. Sistem Gudang tidak mencatat status atau transaksi pembayaran.</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                @foreach($summary as $label => $total)
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
                        <p class="text-xl font-bold tabular-nums">Rp{{ number_format($total['sale'], 2, ',', '.') }}</p>
                        <p class="text-xs text-slate-500">{{ $total['units'] }} unit · HPP penjual Rp{{ number_format($total['cost'], 2, ',', '.') }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-3">Dokumen</th><th class="px-4 py-3">Penjual → Pembeli</th><th class="px-4 py-3">Nomor SN</th><th class="px-4 py-3">Barang</th><th class="px-4 py-3">Kondisi</th><th class="px-4 py-3">Status</th>
                    @if($canSeePrice)<th class="px-4 py-3 text-right">HPP Penjual</th><th class="px-4 py-3 text-right">Harga jual</th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($rows as $row)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('warehouse.branch-sales.show', $row['sale']) }}" class="font-semibold text-sky-700 hover:underline dark:text-sky-400">{{ $row['reference_number'] }}</a><div class="text-xs text-slate-500">{{ $row['created_at']->format('d/m/Y H:i') }}</div></td>
                        <td class="px-4 py-3">{{ $row['from_pop']->name }} → {{ $row['to_pop']->name }}</td>
                        <td class="px-4 py-3 font-mono">{{ $row['serial_number'] }}</td>
                        <td class="px-4 py-3">{{ $row['item_name'] }}</td>
                        <td class="px-4 py-3"><x-warehouse.condition-badge :condition="$row['condition'] ?? \App\Enums\ItemCondition::NEW" :checked="$row['condition_checked']" /></td>
                        <td class="px-4 py-3">{{ $statusLabels[$row['status']] }}</td>
                        @if($canSeePrice)
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['cost_price'] === null ? '—' : 'Rp'.number_format((float) $row['cost_price'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-semibold tabular-nums">Rp{{ number_format((float) $row['sale_price'], 2, ',', '.') }}</td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canSeePrice ? 8 : 6 }}" class="px-4 py-10 text-center text-slate-500">Belum ada SN pada filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $rows->links() }}
</div>
@endsection
