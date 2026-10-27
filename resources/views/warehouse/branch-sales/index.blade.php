@extends('layouts.app')

@section('title', 'Jual Beli SN Antar Cabang')
@section('page_title', 'Jual Beli Antar Cabang')

@section('content')
<x-warehouse.header active="branch-sales" title="Jual Beli SN Antar Cabang" subtitle="Dokumen jual beli dan nominal acuan untuk cross-check pembayaran internal." />

<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-slate-600 dark:text-slate-400">Hanya barang ber-SN pabrikan atau SN sistem yang dapat dijual antar Cabang.</p>
        @if(auth()->user()->hasPermission('warehouse_branch_sale.create'))
            <a href="{{ route('warehouse.branch-sales.create') }}" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">Buat jual beli</a>
        @endif
    </div>
    <form method="GET" class="grid gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-2 lg:grid-cols-5">
        <select name="from_pop_id" aria-label="Cabang penjual" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">Semua penjual</option>
            @foreach($pops as $pop)<option value="{{ $pop->id }}" @selected(request('from_pop_id') == $pop->id)>{{ $pop->name }}</option>@endforeach
        </select>
        <select name="to_pop_id" aria-label="Cabang pembeli" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
            <option value="">Semua pembeli</option>
            @foreach($pops as $pop)<option value="{{ $pop->id }}" @selected(request('to_pop_id') == $pop->id)>{{ $pop->name }}</option>@endforeach
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" aria-label="Tanggal mulai" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
        <input type="date" name="date_to" value="{{ request('date_to') }}" aria-label="Tanggal akhir" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800">
        <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white">Terapkan filter</button>
    </form>
    @if($canSeePrice)
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-slate-700 dark:border-sky-900 dark:bg-slate-900 dark:text-slate-200">
            <h2 class="font-semibold">Cross-check pembayaran internal</h2>
            <p class="mt-1">Cocokkan nomor dokumen, Cabang, periode, dan nominal di laporan ini dengan pembayaran internal. Sistem Gudang tidak mencatat status atau transaksi pembayaran.</p>
            <p class="mt-3 text-xs uppercase tracking-wide text-slate-500">Total nominal final sesuai filter</p>
            <p class="text-xl font-bold tabular-nums">Rp{{ number_format($totalForCrossCheck, 2, ',', '.') }}</p>
        </div>
    @endif
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800">
                <tr><th class="px-4 py-3">Dokumen</th><th class="px-4 py-3">Penjual → Pembeli</th><th class="px-4 py-3">SN</th><th class="px-4 py-3">Fisik</th>@if($canSeePrice)<th class="px-4 py-3 text-right">Acuan cross-check</th>@endif</tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($sales as $sale)
                    <tr>
                        <td class="px-4 py-3"><a href="{{ route('warehouse.branch-sales.show', $sale) }}" class="font-semibold text-sky-700 hover:underline dark:text-sky-400">{{ $sale->reference_number }}</a><div class="text-xs text-slate-500">{{ $sale->created_at->format('d/m/Y H:i') }}</div></td>
                        <td class="px-4 py-3">{{ $sale->fromPop->name }} → {{ $sale->toPop->name }}</td>
                        <td class="px-4 py-3">{{ $sale->lines_count }}</td>
                        <td class="px-4 py-3">{{ $sale->transfer->status->label() }}</td>
                        @if($canSeePrice)<td class="px-4 py-3 text-right font-semibold">{{ $sale->posted_at ? 'Rp'.number_format($sale->posted_total, 2, ',', '.') : 'Belum final' }}</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $canSeePrice ? 5 : 4 }}" class="px-4 py-10 text-center text-slate-500">Belum ada jual beli pada filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $sales->links() }}
</div>
@endsection
