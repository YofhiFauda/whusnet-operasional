@extends('layouts.app')

@section('title', 'Riwayat Pengambilan Alat - Whusnet Operasional')
@section('page_title', 'Riwayat Pengambilan Alat')

@section('content')
@php
    $inputClass = 'w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all placeholder:text-slate-400';
    $canReceive = auth()->user()->hasPermission('warehouse_reassign.create');
    $hasActiveFilters = !empty($filters['q']) || !empty($filters['technician']) || !empty($filters['status']) || !empty($filters['source']) || !empty($filters['from']) || !empty($filters['to']);
@endphp

<x-warehouse.header active="retrievals" title="Riwayat Pengambilan Alat" subtitle="Rekapitulasi log audit penarikan modem, riwayat teknisi pengambil, status transit, hingga konfirmasi penerimaan di rak gudang cabang." />

<x-warehouse.returns-nav active="retrievals" />

{{-- Summary Metric Cards --}}
<div class="mb-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
        <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
        </div>
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Total Log Pengambilan</p>
            <div class="flex items-baseline gap-2">
                <span class="text-xl font-extrabold text-slate-900 dark:text-white tabular-nums">{{ $logs->total() }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">record tercatat</span>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Pencatatan Lengkap</p>
            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 mt-0.5">Audit log terlacak per SN</p>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 flex items-center gap-3.5 shadow-xs">
        <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Scope Gudang</p>
            <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 mt-0.5">Sesuai Hak Akses Cabang</p>
        </div>
    </div>
</div>

{{-- Filter Form --}}
<form method="GET" action="{{ route('warehouse.retrievals.index') }}" class="mb-5 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-5 shadow-xs space-y-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3.5">
        {{-- Search Input --}}
        <div class="sm:col-span-2 lg:col-span-4">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Pencarian Cepat</label>
            <div class="relative">
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari SN, nama pelanggan, atau CID..." class="{{ $inputClass }} pl-9.5">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>

        {{-- Teknisi --}}
        <div class="lg:col-span-3">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Teknisi / Petugas</label>
            <select name="technician" class="{{ $inputClass }} cursor-pointer">
                <option value="">Semua Teknisi / Petugas</option>
                @foreach($technicians as $tech)
                <option value="{{ $tech->id }}" @selected((string) ($filters['technician'] ?? '') === (string) $tech->id)>{{ $tech->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Status --}}
        <div class="lg:col-span-2">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Status Log</label>
            <select name="status" class="{{ $inputClass }} cursor-pointer">
                <option value="">Semua Status</option>
                <option value="transit" @selected(($filters['status'] ?? '') === 'transit')>Transit (di teknisi)</option>
                <option value="diterima" @selected(($filters['status'] ?? '') === 'diterima')>Sudah Diterima</option>
            </select>
        </div>

        {{-- Sumber --}}
        <div class="lg:col-span-3">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Sumber Penarikan</label>
            <select name="source" class="{{ $inputClass }} cursor-pointer">
                <option value="">Semua Sumber</option>
                @foreach($sources as $source)
                <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Date Range & Submit / Reset --}}
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-700/60">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-bold text-slate-600 dark:text-slate-400">Periode:</span>
            <div class="flex items-center gap-1.5">
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="text-xs font-semibold px-2.5 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200" title="Dari Tanggal">
                <span class="text-slate-400 text-xs">s/d</span>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="text-xs font-semibold px-2.5 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200" title="Sampai Tanggal">
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if($hasActiveFilters)
            <a href="{{ route('warehouse.retrievals.index') }}" class="px-3 py-2 text-xs font-bold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                Reset Filter
            </a>
            @endif
            <button type="submit" class="inline-flex items-center justify-center gap-1.5 px-5 py-2 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white rounded-xl text-xs font-bold shadow-xs shadow-sky-600/25 transition-all cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <span>Terapkan Filter</span>
            </button>
        </div>
    </div>
</form>

{{-- Table / Cards Container --}}
<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden">
    @if($logs->isEmpty())
    <div class="p-12 sm:p-16 text-center">
        <div class="w-14 h-14 rounded-2xl bg-slate-100 dark:bg-slate-700/60 text-slate-400 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
        </div>
        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 mb-1.5">Belum Ada Riwayat Pengambilan Alat</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
            Tidak ditemukan riwayat penarikan perangkat yang cocok dengan kriteria filter atau belum ada log tercatat.
        </p>
        @if($hasActiveFilters)
        <a href="{{ route('warehouse.retrievals.index') }}" class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-xl text-xs font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40 hover:bg-sky-100 transition-colors">
            <span>Bersihkan Semua Filter</span>
        </a>
        @endif
    </div>
    @else

    {{-- Desktop / Laptop Table View (Hidden on Small Screens) --}}
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-200/80 dark:border-slate-700/80 font-bold">
                <tr>
                    <th class="text-left px-4 py-3.5">Waktu Penarikan</th>
                    <th class="text-left px-4 py-3.5">Pelanggan</th>
                    <th class="text-left px-4 py-3.5">SN & Model</th>
                    <th class="text-left px-4 py-3.5">Teknisi / Petugas</th>
                    <th class="text-left px-4 py-3.5">Sumber</th>
                    <th class="text-left px-4 py-3.5">Status & Gudang</th>
                    <th class="text-left px-4 py-3.5">Kondisi</th>
                    <th class="text-right px-4 py-3.5">Foto</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($logs as $log)
                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40 transition-colors">
                    <td class="px-4 py-3.5 whitespace-nowrap align-top">
                        <span class="font-bold text-slate-800 dark:text-slate-200 block">{{ $log->retrieved_at->translatedFormat('d M Y') }}</span>
                        <span class="text-[11px] text-slate-400 block">{{ $log->retrieved_at->translatedFormat('H:i') }} WIB</span>
                    </td>
                    <td class="px-4 py-3.5 align-top">
                        @if($log->customer)
                        <a href="{{ route('customers.show', $log->customer) }}" class="font-bold text-sky-600 dark:text-sky-400 hover:underline block">
                            {{ $log->customer->full_name }}
                        </a>
                        <span class="font-mono text-[10px] text-slate-500 dark:text-slate-400">
                            {{ $log->customer->cid ?? $log->customer->customer_code }}
                        </span>
                        @else
                        <span class="text-slate-400 italic">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 align-top">
                        <span class="font-mono font-bold text-slate-900 dark:text-slate-100 bg-slate-100 dark:bg-slate-700/60 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-700 block w-fit">
                            {{ $log->serial_number }}
                        </span>
                        <span class="text-slate-600 dark:text-slate-300 font-medium block mt-1">
                            {{ $log->item?->name ?? 'Model tidak tercatat' }}
                        </span>
                    </td>
                    <td class="px-4 py-3.5 align-top">
                        <span class="font-bold text-slate-800 dark:text-slate-200 block">
                            {{ $log->retrievedBy?->name ?? '-' }}
                        </span>
                    </td>
                    <td class="px-4 py-3.5 align-top">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                            {{ $log->source->label() }}
                        </span>
                    </td>
                    <td class="px-4 py-3.5 align-top">
                        @if($log->isReceived())
                        <div class="space-y-0.5">
                            <span class="inline-flex items-center gap-1 font-bold text-emerald-600 dark:text-emerald-400 text-xs">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                <span>Diterima Gudang</span>
                            </span>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                {{ $log->warehousePop?->name }} · {{ $log->receivedBy?->name ?? '-' }}
                            </p>
                            <p class="text-[10px] text-slate-400">
                                {{ $log->received_at->translatedFormat('d M Y, H:i') }}
                            </p>
                        </div>
                        @else
                        <div class="space-y-0.5">
                            <span class="inline-flex items-center gap-1 font-bold text-amber-600 dark:text-amber-400 text-xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                <span>Transit di Teknisi</span>
                            </span>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                Tujuan: {{ $log->warehousePop?->name ?? 'Gudang Cabang' }}
                            </p>
                        </div>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 align-top">
                        @if($log->condition)
                        <x-ui.badge :variant="$log->condition->badgeVariant()">{{ $log->condition->label() }}</x-ui.badge>
                        @else
                        <span class="text-slate-400 text-[11px] italic">Belum dicek</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 align-top text-right">
                        @if($log->photoPath())
                        <a href="{{ Storage::disk('public')->url($log->photoPath()) }}" target="_blank" rel="noopener"
                           class="group relative inline-block w-8 h-8 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 bg-slate-100 hover:ring-2 hover:ring-sky-500 transition-all"
                           title="Lihat Foto Bukti">
                            <img src="{{ Storage::disk('public')->url($log->photoPath()) }}" alt="Foto" class="w-full h-full object-cover">
                        </a>
                        @else
                        <span class="text-slate-300 dark:text-slate-600 text-xs">-</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Tablet & Mobile Responsive Cards (Visible < lg) --}}
    <div class="block lg:hidden divide-y divide-slate-100 dark:divide-slate-700/60">
        @foreach($logs as $log)
        <div class="p-4 space-y-3 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <span class="font-mono font-bold text-xs bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded-md border border-slate-200 dark:border-slate-600 text-slate-800 dark:text-slate-200 inline-block">
                        {{ $log->serial_number }}
                    </span>
                    <p class="font-semibold text-slate-800 dark:text-slate-200 text-xs mt-1">
                        {{ $log->item?->name ?? 'Model tidak tercatat' }}
                    </p>
                </div>
                <div>
                    @if($log->isReceived())
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                        <svg class="w-3 h-3 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        <span>Diterima</span>
                    </span>
                    @else
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        <span>Transit</span>
                    </span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200/60 dark:border-slate-700/50 text-xs">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Pelanggan</span>
                    @if($log->customer)
                    <a href="{{ route('customers.show', $log->customer) }}" class="font-bold text-sky-600 dark:text-sky-400 truncate block">
                        {{ $log->customer->full_name }}
                    </a>
                    <span class="font-mono text-[10px] text-slate-500 dark:text-slate-400">
                        {{ $log->customer->cid ?? $log->customer->customer_code }}
                    </span>
                    @else
                    <span class="text-slate-400 italic">-</span>
                    @endif
                </div>
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Teknisi Pengambil</span>
                    <p class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ $log->retrievedBy?->name ?? '-' }}</p>
                    <p class="text-[10px] text-slate-400">{{ $log->retrieved_at->translatedFormat('d M Y, H:i') }}</p>
                </div>
            </div>

            <div class="flex items-center justify-between text-xs pt-1">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                        {{ $log->source->label() }}
                    </span>
                    @if($log->condition)
                    <x-ui.badge :variant="$log->condition->badgeVariant()">{{ $log->condition->label() }}</x-ui.badge>
                    @endif
                </div>
                @if($log->photoPath())
                <a href="{{ Storage::disk('public')->url($log->photoPath()) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-bold text-sky-600 dark:text-sky-400 hover:underline">
                    <span>Lihat Foto</span>
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                </a>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    @if($logs->hasPages())
    <div class="px-4 py-3.5 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-900/30">
        {{ $logs->links() }}
    </div>
    @endif
    @endif
</div>

@endsection
