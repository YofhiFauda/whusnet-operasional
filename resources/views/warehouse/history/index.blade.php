@extends('layouts.app')

@section('title', 'Riwayat Mutasi Gudang - Whusnet Operasional')
@section('page_title', 'Riwayat Mutasi Gudang')

@section('content')

<x-warehouse.header active="history" title="Riwayat Mutasi Gudang" subtitle="Semua pergerakan barang lintas gudang — klik baris buat buka dokumen sumbernya (Transfer/Issue/Barang Masuk)." />

<!-- Filter -->
<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 mb-6 shadow-xs">
    <form action="{{ route('warehouse.history.index') }}" method="GET" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Tipe Mutasi</label>
            <select name="type" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option value="">Semua Tipe</option>
                @foreach($types as $type)
                <option value="{{ $type->value }}" {{ (string) $typeFilter === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Gudang</label>
            <select name="pop_id" class="px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option value="">Semua Gudang</option>
                @foreach($pops as $pop)
                <option value="{{ $pop->id }}" {{ (string) $popFilter === (string) $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50">Filter</button>
        @if($typeFilter || $popFilter)
        <a href="{{ route('warehouse.history.index') }}" class="text-sm text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200">Reset</a>
        @endif
    </form>
</div>

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
    @if($ledger->isEmpty())
    <div class="p-16 text-center">
        <p class="text-sm font-bold text-slate-700 dark:text-slate-300">Gak ada mutasi yang cocok filter ini.</p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Waktu</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Tipe</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang / SN</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Asal &amp; Tujuan</th>
                    <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Dicatat Oleh</th>
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($ledger as $txn)
                @php
                    $typeBadge = match($txn->type->value ?? '') {
                        'receive' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                        'transfer' => 'bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800',
                        'issue' => 'bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 border-indigo-200 dark:border-indigo-800',
                        'return' => 'bg-teal-50 dark:bg-teal-950/40 text-teal-700 dark:text-teal-400 border-teal-200 dark:border-teal-800',
                        'adjustment' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
                        'install' => 'bg-violet-50 dark:bg-violet-950/40 text-violet-700 dark:text-violet-400 border-violet-200 dark:border-violet-800',
                        default => 'bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600',
                    };

                    $detailRoute = match($txn->type->value ?? '') {
                        'receive' => auth()->user()->hasPermission('warehouse_transfer.view') && $txn->reference_number
                            ? route('warehouse.receive.show', $txn->reference_number) : null,
                        'transfer' => auth()->user()->hasPermission('warehouse_transfer.view') && $txn->inventory_transfer_id
                            ? route('warehouse.transfers.show', $txn->inventory_transfer_id) : null,
                        'issue' => auth()->user()->hasPermission('warehouse_issue.view') && $txn->reference_number
                            ? route('warehouse.issues.show', $txn->reference_number) : null,
                        default => null,
                    };

                    // Transfer nulis 2 baris ledger per pergerakan (dispatch +
                    // confirm) pake reference_number SAMA — badge generik bikin
                    // keliatan kayak duplikat (laporan user 2026-09-03). Dibedain.
                    $typeLabel = match(true) {
                        $txn->type->value === 'transfer' && $txn->from_pop_id !== null => 'Transfer Dikirim',
                        $txn->type->value === 'transfer' && $txn->to_pop_id !== null => 'Transfer Diterima',
                        default => $txn->type->label(),
                    };
                @endphp
                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors {{ $detailRoute ? 'cursor-pointer' : '' }}" @if($detailRoute) onclick="window.location='{{ $detailRoute }}'" @endif>
                    <td class="px-6 py-3.5 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                        <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $txn->created_at->translatedFormat('d M Y') }}</span>
                        <span class="text-slate-400">{{ $txn->created_at->format('H:i') }}</span>
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap">
                        <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $typeBadge }}">{{ $typeLabel }}</span>
                    </td>
                    <td class="px-6 py-3.5">
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">
                            @if($detailRoute)
                            <a href="{{ $detailRoute }}" class="hover:underline hover:text-sky-600 dark:hover:text-sky-400">{{ $txn->item->name }}</a>
                            @else
                            {{ $txn->item->name }}
                            @endif
                        </div>
                        @if($txn->serial)
                        <div class="text-xs font-mono text-sky-600 dark:text-sky-400 mt-0.5">
                            @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                            <a href="{{ route('warehouse.traceability.index', ['sn' => $txn->serial->serial_number]) }}" onclick="event.stopPropagation()" class="hover:underline">SN: {{ $txn->serial->serial_number }}</a>
                            @else
                            <span>SN: {{ $txn->serial->serial_number }}</span>
                            @endif
                        </div>
                        @elseif($txn->lot_no)
                        <div class="text-xs font-mono text-slate-400 mt-0.5">Lot: {{ $txn->lot_no }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap text-xs text-slate-600 dark:text-slate-300">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700/60 font-medium">{{ $txn->fromPop->name ?? ($txn->fromTechnician->name ?? 'Pengadaan (Baru)') }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700/60 font-medium">{{ $txn->toPop->name ?? ($txn->toTechnician->name ?? 'Pelanggan / Luar') }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap text-right font-mono text-sm font-bold text-slate-800 dark:text-slate-200">
                        {{ $txn->serial ? '1 unit' : rtrim(rtrim(number_format((float) $txn->qty, 2, ',', '.'), '0'), ',') }}
                        <span class="text-xs font-normal text-slate-400">{{ $txn->serial ? '' : $txn->item->unit }}</span>
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">{{ $txn->createdBy?->name ?? '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60">
        {{ $ledger->links() }}
    </div>
    @endif
</div>

@endsection
