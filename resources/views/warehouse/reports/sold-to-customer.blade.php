@extends('layouts.app')

@section('title', 'Laporan Barang Terjual ke Pelanggan - Whusnet Operasional')
@section('page_title', 'Laporan Barang Terjual ke Pelanggan')

@section('content')

<x-warehouse.header
    active="reports"
    title="Laporan Barang Terjual ke Pelanggan"
    subtitle="Daftar unit SN yang dijual (Beli dari Gudang) ke pelanggan — dibedakan dari Sewa, data mentahnya dari pencatatan ownership modem sewa/beli."
    :back-url="route('warehouse.reports.index')"
/>

<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-xl shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/40">
                <tr>
                    <th class="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 whitespace-nowrap">Tanggal</th>
                    <th class="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 whitespace-nowrap">SN</th>
                    <th class="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 whitespace-nowrap">Nama Barang</th>
                    <th class="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 whitespace-nowrap">Pelanggan</th>
                    <th class="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 whitespace-nowrap">Cabang</th>
                    <th class="px-3 py-2.5 text-right text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 whitespace-nowrap">Harga</th>
                    <th class="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 whitespace-nowrap">Sumber</th>
                    <th class="px-3 py-2.5 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 whitespace-nowrap">Dicatat Oleh</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($sales as $sale)
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30">
                    <td class="px-3 py-2.5 whitespace-nowrap text-slate-700 dark:text-slate-300">{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-2.5 whitespace-nowrap font-mono text-slate-700 dark:text-slate-300">{{ $sale->inventorySerial->serial_number ?? '—' }}</td>
                    <td class="px-3 py-2.5 whitespace-nowrap text-slate-700 dark:text-slate-300">{{ $sale->inventorySerial->item->name ?? '(barang dihapus)' }}</td>
                    <td class="px-3 py-2.5 whitespace-nowrap text-slate-700 dark:text-slate-300">{{ $sale->customer->full_name ?? '(pelanggan dihapus)' }}</td>
                    <td class="px-3 py-2.5 whitespace-nowrap text-slate-700 dark:text-slate-300">{{ $sale->customer->pop->name ?? '—' }}</td>
                    <td class="px-3 py-2.5 whitespace-nowrap text-right font-mono text-slate-700 dark:text-slate-300">Rp {{ number_format((float) $sale->price, 0, ',', '.') }}</td>
                    <td class="px-3 py-2.5 whitespace-nowrap">
                        @if($sale->fop_task_id)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200/60 dark:border-sky-800/60">
                                {{ $sale->fopTask?->category?->label() ?? 'FOP Task' }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200/60 dark:border-slate-700/60">
                                PSB (Langsung)
                            </span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 whitespace-nowrap text-slate-700 dark:text-slate-300">{{ $sale->createdBy->name ?? '—' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-3 py-8 text-center text-slate-500 dark:text-slate-400">
                        Belum ada barang yang terjual ke pelanggan dalam akses Anda.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
