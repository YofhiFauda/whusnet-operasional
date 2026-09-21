@extends('layouts.app')

@section('title', 'Konfirmasi Barang Transfer - Whusnet Operasional')
@section('page_title', 'Konfirmasi Barang Transfer')

@section('content')

<x-warehouse.header active="transfers-pending" title="Konfirmasi Barang Transfer" subtitle="Daftar transfer yang masih dalam perjalanan (in transit) — Pusat & Cabang bisa cek status konfirmasinya di sini." backUrl="{{ route('warehouse.stock.index') }}" />

<div class="space-y-6">

    {{-- Perlu dikonfirmasi di sisi user ini (scope-nya masuk to_pop) --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/80 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-100 dark:border-amber-800/60 shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Perlu Dikonfirmasi Di Sini</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Transfer yang sudah dikirim dan menunggu konfirmasi terima dari gudang Anda.</p>
            </div>
            <span class="ml-auto inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                {{ $actionable->count() }}
            </span>
        </div>

        @if($actionable->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                Tidak ada transfer yang menunggu konfirmasi Anda saat ini.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                        <tr>
                            <th class="text-left font-semibold px-5 py-2.5">Referensi</th>
                            <th class="text-left font-semibold px-5 py-2.5">Dari → Ke</th>
                            <th class="text-left font-semibold px-5 py-2.5">Jumlah Baris</th>
                            <th class="text-left font-semibold px-5 py-2.5">Dikirim</th>
                            <th class="text-right font-semibold px-5 py-2.5">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($actionable as $transfer)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/60">
                            <td class="px-5 py-3 font-semibold text-slate-900 dark:text-slate-100">{{ $transfer->reference_number }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $transfer->fromPop->name }} <span class="text-slate-400">→</span> {{ $transfer->toPop->name }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $transfer->line_count }} baris</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">
                                {{ $transfer->created_at->translatedFormat('d M Y • H:i') }} WIB
                                <div class="text-xs">oleh {{ $transfer->createdBy?->name ?? 'Sistem' }}</div>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('warehouse.transfers.show', $transfer) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-xs transition">
                                    Tinjau &amp; Konfirmasi
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Dikirim dari sini, masih menunggu cabang tujuan konfirmasi --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg shadow-xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 dark:border-slate-700/80 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-800/60 shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Terkirim, Menunggu Cabang Tujuan</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Transfer yang Anda kirim, masih menunggu gudang tujuan mengonfirmasi penerimaan.</p>
            </div>
            <span class="ml-auto inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                {{ $awaitingOtherSide->count() }}
            </span>
        </div>

        @if($awaitingOtherSide->isEmpty())
            <div class="px-5 py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                Tidak ada transfer yang sedang menunggu konfirmasi dari cabang lain.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide">
                        <tr>
                            <th class="text-left font-semibold px-5 py-2.5">Referensi</th>
                            <th class="text-left font-semibold px-5 py-2.5">Dari → Ke</th>
                            <th class="text-left font-semibold px-5 py-2.5">Jumlah Baris</th>
                            <th class="text-left font-semibold px-5 py-2.5">Dikirim</th>
                            <th class="text-right font-semibold px-5 py-2.5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($awaitingOtherSide as $transfer)
                        <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/60">
                            <td class="px-5 py-3 font-semibold text-slate-900 dark:text-slate-100">{{ $transfer->reference_number }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $transfer->fromPop->name }} <span class="text-slate-400">→</span> {{ $transfer->toPop->name }}</td>
                            <td class="px-5 py-3 text-slate-600 dark:text-slate-300">{{ $transfer->line_count }} baris</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">
                                {{ $transfer->created_at->translatedFormat('d M Y • H:i') }} WIB
                                <div class="text-xs">oleh {{ $transfer->createdBy?->name ?? 'Sistem' }}</div>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                    Dalam Perjalanan
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

@endsection
