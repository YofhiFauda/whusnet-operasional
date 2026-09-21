@extends('layouts.app')

@section('title', 'Terima Retur — Whusnet Operasional')
@section('page_title', 'Terima Retur')

@section('content')

<x-warehouse.header active="custody" title="Terima Retur Modem" subtitle="Modem hasil pengambilan alat (putus langganan) yang masih dipegang teknisi. Periksa fisiknya, lalu terima ke stok Gudang cabang." />

<div class="mb-4 flex items-center gap-4 text-xs font-bold">
    <a href="{{ route('warehouse.returns.from-customer.create') }}" class="text-sky-600 dark:text-sky-400">Terima modem dari pelanggan →</a>
    <a href="{{ route('warehouse.retrievals.index') }}" class="text-sky-600 dark:text-sky-400">Riwayat pengambilan alat →</a>
</div>

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg shadow-xs overflow-hidden">
    @if($serials->isEmpty())
    <div class="p-8 text-center text-sm text-slate-500 dark:text-slate-400">
        Tidak ada modem yang menunggu diterima.
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50 dark:bg-slate-900/40 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px]">
                <tr>
                    <th class="text-left px-4 py-3 font-bold">SN</th>
                    <th class="text-left px-4 py-3 font-bold">Barang</th>
                    <th class="text-left px-4 py-3 font-bold">Dari Pelanggan</th>
                    <th class="text-left px-4 py-3 font-bold">Dipegang Teknisi</th>
                    <th class="text-left px-4 py-3 font-bold">Gudang Tujuan</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($serials as $serial)
                <tr>
                    <td class="px-4 py-3 font-mono font-bold text-sky-600 dark:text-sky-400">{{ $serial->serial_number }}</td>
                    <td class="px-4 py-3 text-slate-800 dark:text-slate-100">{{ $serial->item?->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $serial->customer?->full_name ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $serial->currentTechnician?->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $serial->issuedFromPop?->name ?? '-' }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('warehouse.returns.receive.create', $serial) }}"
                           class="inline-flex items-center px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-[11px] font-bold transition-all">
                            Terima
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700/60">{{ $serials->links() }}</div>
    @endif
</div>

@endsection
