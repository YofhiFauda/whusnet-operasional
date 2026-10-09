@extends('layouts.app')

@section('title', 'Terima di Pusat — Whusnet Operasional')
@section('page_title', 'Terima di Pusat')

@section('content')

<x-warehouse.header active="returns" title="Terima Retur di Gudang Pusat" subtitle="Modem retur dalam pengiriman dari Cabang (Tahap 2). Periksa fisik di sini — kondisi final ditentukan sekarang, stok Pusat baru bertambah setelah dikonfirmasi." />

<x-warehouse.returns-nav active="pusat" />

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden">
    @if($serials->isEmpty())
    <div class="p-12 text-center text-sm text-slate-500 dark:text-slate-400">
        Tidak ada modem retur yang menunggu konfirmasi Pusat.
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-200/80 dark:border-slate-700/80 font-bold">
                <tr>
                    <th class="text-left px-4 py-3.5">SN & Model</th>
                    <th class="text-left px-4 py-3.5">Asal</th>
                    <th class="text-left px-4 py-3.5">Observasi Cabang</th>
                    <th class="text-right px-4 py-3.5">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($serials as $serial)
                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40">
                    <td class="px-4 py-3.5">
                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400">{{ $serial->serial_number }}</span>
                        <span class="text-slate-700 dark:text-slate-300 block">{{ $serial->item?->name ?? 'Model belum ditentukan' }}</span>
                    </td>
                    <td class="px-4 py-3.5"><x-warehouse.origin-badge :log="$serial->latestRetrievalLog" /></td>
                    <td class="px-4 py-3.5"><x-warehouse.condition-badge :condition="$serial->condition" :checked="false" /></td>
                    <td class="px-4 py-3.5 text-right">
                        <a href="{{ route('warehouse.returns.pusat.create', $serial) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-sky-600/25">
                            Periksa & Terima
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@endsection
