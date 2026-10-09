@extends('layouts.app')

@section('title', 'Terima Retur Modem — Whusnet Operasional')
@section('page_title', 'Terima Retur Modem')

@section('content')

<x-warehouse.header active="returns" title="Terima Retur Modem" subtitle="Modem hasil pengambilan alat teknisi (putus langganan) yang berstatus transit. Periksa fisik & data laporan pengembalian teknisi, lalu konfirmasi terima ke gudang cabang." />

<x-warehouse.returns-nav active="returns" :transitCount="$serials->total()" />

{{-- Summary Alert/Stats Banner --}}
<div class="mb-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
    <div class="bg-gradient-to-br from-amber-500/10 via-amber-500/5 to-transparent dark:from-amber-500/15 dark:via-transparent border border-amber-200/80 dark:border-amber-800/60 rounded-2xl p-4 flex items-center gap-3.5">
        <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 shadow-sm shadow-amber-500/30">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Menunggu Diterima</p>
            <div class="flex items-baseline gap-2">
                <span class="text-xl font-extrabold text-slate-900 dark:text-white tabular-nums">{{ $serials->total() }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">unit modem transit</span>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 flex items-center gap-3.5">
        <div class="w-10 h-10 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
        </div>
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Teknisi Bertugas</p>
            <div class="flex items-baseline gap-2">
                <span class="text-xl font-extrabold text-slate-900 dark:text-white tabular-nums">{{ $technicians->count() }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium">orang pengambil</span>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 flex items-center gap-3.5">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
            </svg>
        </div>
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Standar Penerimaan</p>
            <p class="text-xs text-slate-700 dark:text-slate-300 font-semibold mt-0.5">Wajib cek fisik & kondisi</p>
        </div>
    </div>
</div>

{{-- Filter Bar --}}
<form method="GET" action="{{ route('warehouse.returns.index') }}" class="mb-5 bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-5 shadow-xs">
    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3.5 items-end">
        <div class="sm:col-span-6 lg:col-span-5">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Pencarian Cepat</label>
            <div class="relative">
                <input type="text" name="q" value="{{ $search }}" placeholder="Cari SN, nama pelanggan, CID, atau model..."
                       class="w-full text-xs font-semibold pl-9 pr-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all placeholder:text-slate-400">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>

        <div class="sm:col-span-4 lg:col-span-4">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Teknisi Pengambil</label>
            <select name="technician" class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all cursor-pointer">
                <option value="">Semua Teknisi</option>
                @foreach($technicians as $tech)
                <option value="{{ $tech->id }}" @selected((string) $technicianId === (string) $tech->id)>{{ $tech->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="sm:col-span-2 lg:col-span-3 flex items-center gap-2">
            <button type="submit" class="flex-1 inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-sky-600/25 transition-all cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <span>Filter</span>
            </button>
            @if($search !== '' || $technicianId)
            <a href="{{ route('warehouse.returns.index') }}" class="px-3 py-2.5 text-xs font-bold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700/60 border border-slate-200 dark:border-slate-700 transition-colors shrink-0" title="Reset filter">
                Reset
            </a>
            @endif
        </div>
    </div>
</form>

{{-- Content Antrean Modem Transit --}}
<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl shadow-xs overflow-hidden">
    @if($serials->isEmpty())
    <div class="p-12 sm:p-16 text-center">
        <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-4 ring-8 ring-emerald-500/10">
            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>
        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 mb-1.5">Tidak Ada Modem Menunggu Diterima</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
            Semua modem hasil pengambilan alat teknisi sudah dikonfirmasi masuk gudang cabang, atau belum ada task penarikan perangkat baru.
        </p>
        @if($search !== '' || $technicianId)
        <a href="{{ route('warehouse.returns.index') }}" class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-xl text-xs font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40 hover:bg-sky-100 transition-colors">
            <span>Bersihkan Pencarian</span>
        </a>
        @endif
    </div>
    @else

    {{-- Desktop / Laptop / Tablet Table View (Hidden on Mobile) --}}
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50/80 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-200/80 dark:border-slate-700/80 font-bold">
                <tr>
                    <th class="text-left px-4 py-3.5">SN & Model</th>
                    <th class="text-left px-4 py-3.5">Pelanggan Asal</th>
                    <th class="text-left px-4 py-3.5">Teknisi Pengambil</th>
                    <th class="text-left px-4 py-3.5">Laporan Lapangan</th>
                    <th class="text-left px-4 py-3.5">Gudang Tujuan</th>
                    <th class="text-right px-4 py-3.5">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @foreach($serials as $serial)
                @php
                    $log = $serial->latestRetrievalLog;
                    $taskReport = $log?->task?->deviceRetrieval;
                    $photo = $log?->photoPath();
                @endphp
                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-900/40 transition-colors group">
                    <td class="px-4 py-4 align-top">
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40 px-2 py-0.5 rounded-md border border-sky-100 dark:border-sky-900/50 block text-xs">
                                {{ $serial->serial_number }}
                            </span>
                        </div>
                        <span class="text-slate-700 dark:text-slate-300 font-semibold block mt-1">
                            {{ $serial->item?->name ?? 'Model belum ditentukan' }}
                        </span>
                        <x-warehouse.origin-badge :log="$serial->latestRetrievalLog" class="mt-1 inline-block" />
                    </td>
                    <td class="px-4 py-4 align-top">
                        @if($serial->customer)
                        <div class="space-y-0.5">
                            <a href="{{ route('customers.show', $serial->customer) }}" target="_blank" class="font-bold text-slate-900 dark:text-slate-100 hover:text-sky-600 dark:hover:text-sky-400 block transition-colors">
                                {{ $serial->customer->full_name }}
                            </a>
                            <span class="inline-flex items-center gap-1 font-mono text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                                <span>CID:</span> {{ $serial->customer->cid ?? $serial->customer->customer_code }}
                            </span>
                        </div>
                        @else
                        <span class="text-slate-400 italic text-[11px]">Data pelanggan tidak terhubung</span>
                        @endif
                    </td>
                    <td class="px-4 py-4 align-top">
                        <div class="flex items-start gap-2">
                            <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center font-bold text-[10px] text-slate-600 dark:text-slate-300 shrink-0 mt-0.5">
                                {{ strtoupper(substr($log?->retrievedBy?->name ?? $serial->currentTechnician?->name ?? 'T', 0, 1)) }}
                            </div>
                            <div>
                                <span class="font-bold text-slate-800 dark:text-slate-200 block">
                                    {{ $log?->retrievedBy?->name ?? $serial->currentTechnician?->name ?? '-' }}
                                </span>
                                @if($log?->retrieved_at)
                                <span class="text-[11px] text-slate-500 dark:text-slate-400 block font-medium">
                                    {{ $log->retrieved_at->translatedFormat('d M Y, H:i') }}
                                </span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 align-top">
                        <div class="flex items-start gap-2.5">
                            @if($photo)
                            <a href="{{ Storage::disk('public')->url($photo) }}" target="_blank" rel="noopener"
                               class="group/img relative w-10 h-10 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 shrink-0 bg-slate-100 hover:ring-2 hover:ring-sky-500 transition-all shadow-xs"
                               title="Lihat Foto Bukti">
                                <img src="{{ Storage::disk('public')->url($photo) }}" alt="Foto Bukti" class="w-full h-full object-cover group-hover/img:scale-110 transition-transform duration-200">
                                <div class="absolute inset-0 bg-black/30 opacity-0 group-hover/img:opacity-100 flex items-center justify-center text-white text-[9px] font-bold transition-opacity">
                                    ↗
                                </div>
                            </a>
                            @endif
                            <div class="text-[11px] space-y-1">
                                @if(!empty($taskReport?->accessories))
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-[10px] font-bold border border-emerald-200/60 dark:border-emerald-800/60">
                                    <svg class="w-3 h-3 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                    <span>{{ count($taskReport->accessories) }} aksesoris</span>
                                </span>
                                @endif
                                @if($taskReport?->notes)
                                <p class="text-slate-600 dark:text-slate-400 line-clamp-2 max-w-[200px] italic leading-tight" title="{{ $taskReport->notes }}">
                                    "{{ $taskReport->notes }}"
                                </p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 align-top">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 font-semibold text-xs border border-slate-200 dark:border-slate-700">
                            <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span>{{ $serial->issuedFromPop?->name ?? '-' }}</span>
                        </span>
                    </td>
                    <td class="px-4 py-4 align-top text-right">
                        <a href="{{ route('warehouse.returns.receive.create', $serial) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white rounded-xl text-xs font-bold shadow-xs shadow-sky-600/25 transition-all hover:scale-[1.02] active:scale-[0.98]">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                            <span>Periksa & Terima</span>
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Mobile Card View (Optimized for Small Screens & Touch) --}}
    <div class="block md:hidden divide-y divide-slate-100 dark:divide-slate-700/60">
        @foreach($serials as $serial)
        @php
            $log = $serial->latestRetrievalLog;
            $taskReport = $log?->task?->deviceRetrieval;
            $photo = $log?->photoPath();
        @endphp
        <div class="p-4 space-y-3.5 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
            {{-- Header Card: SN + Badge Status --}}
            <div class="flex items-start justify-between gap-2">
                <div>
                    <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/50 px-2 py-0.5 rounded-md border border-sky-100 dark:border-sky-900/60 text-xs inline-block">
                        {{ $serial->serial_number }}
                    </span>
                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-xs mt-1">
                        {{ $serial->item?->name ?? 'Model belum ditentukan' }}
                    </h4>
                    <x-warehouse.origin-badge :log="$log" class="mt-1 inline-block" />
                </div>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60 shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    <span>Transit</span>
                </span>
            </div>

            {{-- Pelanggan & Petugas --}}
            <div class="grid grid-cols-2 gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200/60 dark:border-slate-700/50 text-xs">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Pelanggan</span>
                    <p class="font-bold text-slate-900 dark:text-slate-100 truncate">
                        {{ $serial->customer?->full_name ?? '-' }}
                    </p>
                    <p class="font-mono text-[10px] text-slate-500 dark:text-slate-400">
                        {{ $serial->customer?->cid ?? $serial->customer?->customer_code ?? '-' }}
                    </p>
                </div>
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Teknisi Pengambil</span>
                    <p class="font-bold text-slate-800 dark:text-slate-200 truncate">
                        {{ $log?->retrievedBy?->name ?? $serial->currentTechnician?->name ?? '-' }}
                    </p>
                    @if($log?->retrieved_at)
                    <p class="text-[10px] text-slate-500 dark:text-slate-400">
                        {{ $log->retrieved_at->translatedFormat('d M H:i') }}
                    </p>
                    @endif
                </div>
            </div>

            {{-- Bukti Foto & Aksesoris --}}
            @if($photo || !empty($taskReport?->accessories) || $taskReport?->notes)
            <div class="flex items-center gap-3 text-xs">
                @if($photo)
                <a href="{{ Storage::disk('public')->url($photo) }}" target="_blank" rel="noopener"
                   class="w-12 h-12 rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 shrink-0 bg-slate-100 relative">
                    <img src="{{ Storage::disk('public')->url($photo) }}" alt="Foto" class="w-full h-full object-cover">
                </a>
                @endif
                <div class="space-y-1 flex-1 min-w-0">
                    @if(!empty($taskReport?->accessories))
                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                        ✓ {{ count($taskReport->accessories) }} aksesoris disertakan
                    </span>
                    @endif
                    @if($taskReport?->notes)
                    <p class="text-[11px] text-slate-600 dark:text-slate-400 italic truncate">
                        "{{ $taskReport->notes }}"
                    </p>
                    @endif
                </div>
            </div>
            @endif

            {{-- Footer Action Button --}}
            <div class="pt-2 flex items-center justify-between gap-2">
                <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <span>{{ $serial->issuedFromPop?->name ?? 'Gudang' }}</span>
                </span>
                <a href="{{ route('warehouse.returns.receive.create', $serial) }}"
                   class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-sky-600/25 transition-all w-full sm:w-auto">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    <span>Periksa & Terima</span>
                </a>
            </div>
        </div>
        @endforeach
    </div>

    @if($serials->hasPages())
    <div class="px-4 py-3.5 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-900/30">
        {{ $serials->links() }}
    </div>
    @endif
    @endif
</div>

@endsection
