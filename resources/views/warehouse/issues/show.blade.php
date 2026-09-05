@extends('layouts.app')

@section('title', 'Bukti Serah Terima '.$reference.' - Whusnet Operasional')
@section('page_title', 'Serah Terima '.$reference)

@section('content')

@php $first = $transactions->first(); @endphp

<x-warehouse.header active="custody" title="Bukti Serah Terima Barang #{{ $reference }}" subtitle="Dokumen serah terima material & perangkat aktif dari gudang cabang ke teknisi lapangan." />

<div class="space-y-6">
    <!-- Header Summary Card -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-6 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100 dark:border-indigo-800/60 shadow-xs shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
                            {{ $first->fromPop->name ?? '-' }} <span class="text-slate-400">→</span> {{ $first->toTechnician->name ?? '-' }}
                        </h3>
                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-400 border border-indigo-200 dark:border-indigo-800">
                            DISERAHKAN
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Dicatat pada <strong class="text-slate-700 dark:text-slate-200">{{ $first->created_at->translatedFormat('d F Y • H:i') }} WIB</strong> oleh <strong class="text-slate-700 dark:text-slate-200">{{ $first->createdBy?->name ?? 'Sistem' }}</strong>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0 print:hidden">
                <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold transition-colors cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.04-.36-2.126-.36-3.238A8.04 8.04 0 0112 2.55a8.04 8.04 0 015.64 8.041c0 1.112-.12 2.198-.36 3.238m-10.56 0A9.956 9.956 0 0012 18.001c2.148 0 4.13-.674 5.64-1.815m-10.56 0L4.5 19.5m15-1.5l2.25 1.5"/></svg>
                    <span>Cetak Bon</span>
                </button>
                <a href="{{ route('warehouse.custody.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                    Kembali ke Custody
                </a>
            </div>
        </div>
    </div>

    <!-- Table of Lines -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs print:border-none print:shadow-none" id="print-area">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60">
            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Rincian Barang yang Diserahkan</h4>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang / Item</th>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Detail / Lot / Serial Number</th>
                        <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah Diserahkan</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                    @foreach($transactions as $line)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-3.5">
                            <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $line->item->name }}</div>
                            <div class="text-[11px] font-mono text-slate-400">{{ $line->item->code }}</div>
                        </td>
                        <td class="px-6 py-3.5 font-mono text-xs text-slate-600 dark:text-slate-300">
                            @if($line->serial)
                                @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                <a href="{{ route('warehouse.traceability.index', ['sn' => $line->serial->serial_number]) }}" class="text-sky-600 dark:text-sky-400 font-bold hover:underline print:text-slate-800">
                                    SN: {{ $line->serial->serial_number }}
                                </a>
                                @else
                                <span class="font-bold">SN: {{ $line->serial->serial_number }}</span>
                                @endif
                            @else
                                <span class="text-slate-400">{{ $line->lot_no ? "Lot: {$line->lot_no}" : '-' }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono text-sm font-bold text-slate-800 dark:text-slate-200">
                            {{ $line->serial ? '1 unit' : rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',').' '.$line->item->unit }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
