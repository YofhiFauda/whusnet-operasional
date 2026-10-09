@props([
    'active' => 'returns',
    'transitCount' => null,
])

@php
    $user = auth()->user();
    $canReassign = $user->hasPermission('warehouse_reassign.create');
    $canViewWarehouse = $user->hasPermission('warehouse.view');

    $isCurrent = fn (string $key) => ($active === $key) || 
        ($key === 'returns' && $active === 'transit');

    $navClass = fn (string $key) => $isCurrent($key)
        ? 'inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold bg-sky-600 dark:bg-sky-500 text-white shadow-sm shadow-sky-600/25 ring-1 ring-sky-500/20 shrink-0 transition-all'
        : 'inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-slate-700/60 border border-slate-200/80 dark:border-slate-700/80 transition-all shrink-0';

    $iconClass = fn (string $key) => $isCurrent($key) ? 'text-white' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300';
@endphp

{{-- Sub-Navigation Navigasi 3 Halaman Pengembalian / Barang Retur --}}
<div class="mb-6 flex items-center gap-2 overflow-x-auto pb-1 no-scrollbar text-xs">
    @if($canReassign)
    <a href="{{ route('warehouse.returns.index') }}"
       class="group {{ $navClass('returns') }}">
        <svg class="w-4 h-4 {{ $iconClass('returns') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
        </svg>
        <span>Terima Retur (Transit)</span>
        @if($transitCount !== null)
        <span class="ml-0.5 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold tabular-nums {{ $isCurrent('returns') ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' }}">
            {{ $transitCount }}
        </span>
        @endif
    </a>

    <a href="{{ route('warehouse.returns.from-customer.create') }}"
       class="group {{ $navClass('from-customer') }}">
        <svg class="w-4 h-4 {{ $iconClass('from-customer') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z"/>
        </svg>
        <span>Terima Modem dari Pelanggan</span>
    </a>

    {{-- Tahap 2 & 3 (ADHOC-108) — SEMUA retur wajib verifikasi Pusat. --}}
    <a href="{{ route('warehouse.returns.dispatch.index') }}"
       class="group {{ $navClass('dispatch') }}">
        <svg class="w-4 h-4 {{ $iconClass('dispatch') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4"/>
        </svg>
        <span>Kirim ke Pusat</span>
    </a>

    <a href="{{ route('warehouse.returns.pusat.index') }}"
       class="group {{ $navClass('pusat') }}">
        <svg class="w-4 h-4 {{ $iconClass('pusat') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>Terima di Pusat</span>
    </a>
    @endif

    @if($canViewWarehouse)
    <a href="{{ route('warehouse.damaged.index') }}"
       class="group {{ $navClass('damaged') }}">
        <svg class="w-4 h-4 {{ $iconClass('damaged') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <span>Modem Rusak</span>
    </a>
    @endif

    @if($canViewWarehouse)
    <a href="{{ route('warehouse.retrievals.index') }}"
       class="group {{ $navClass('retrievals') }}">
        <svg class="w-4 h-4 {{ $iconClass('retrievals') }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
        </svg>
        <span>Riwayat Pengambilan Alat</span>
    </a>
    @endif
</div>
