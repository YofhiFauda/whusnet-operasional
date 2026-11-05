@extends('layouts.app')

@section('title', 'Restriksi Paket - Whusnet Operasional')
@section('page_title', 'Restriksi Paket per Role')

@section('content')
<div x-data="{
    search: '',
    matches(pkg) {
        if (!this.search.trim()) return true;
        const q = this.search.toLowerCase();
        return (pkg.name && pkg.name.toLowerCase().includes(q)) ||
               (pkg.code && pkg.code.toLowerCase().includes(q));
    }
}">

    {{-- ── LAYER 2: KETERANGAN (NAKED-STYLE CARD, DESKRIPSI DIRINGKAS DI MOBILE) ── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-4 sm:p-6 mb-5">
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Daftar Paket Terbatas</h3>

        {{-- Versi lengkap — desktop/tablet --}}
        <p class="hidden sm:block text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-3xl">
            Paket yang dicentang di bawah ini adalah SATU-SATUNYA paket yang boleh dipilih role berikut saat registrasi/ubah paket pelanggan:
            @if($restrictedRoles->isEmpty())
                <span class="italic">belum ada role yang ditandai restricted.</span>
            @else
                @foreach($restrictedRoles as $role)
                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60 ml-1">{{ $role->name }}</span>
                @endforeach
            @endif
            Role lain (tanpa penanda ini) tetap melihat semua paket aktif. Menambah/mencabut role dari daftar restriksi dilakukan lewat halaman <span class="font-semibold">Role Management</span>.
        </p>

        {{-- Versi ringkas — mobile, cukup role mana yang terdampak --}}
        <div class="sm:hidden mt-1.5 flex items-center gap-1.5 flex-wrap text-[11px] text-slate-500 dark:text-slate-400">
            <span>Berlaku untuk:</span>
            @if($restrictedRoles->isEmpty())
                <span class="italic">belum ada role restricted</span>
            @else
                @foreach($restrictedRoles as $role)
                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60">{{ $role->name }}</span>
                @endforeach
            @endif
        </div>
    </div>

    {{-- ── LAYER 2: FILTER CARD (EXPANDABLE / COLLAPSIBLE ON MOBILE, SESUAI DESIGN.MD) ── --}}
    <div x-data="{
            mobileFilterOpen: false,
            isDesktop: window.innerWidth >= 768
         }"
         @resize.window.debounce.100ms="isDesktop = window.innerWidth >= 768"
         class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-xl shadow-2xs mb-5 overflow-hidden transition-all">

        <!-- Header Filter: Mobile Toggle & Status Info -->
        <div class="px-4 py-3 flex items-center justify-between gap-3 bg-slate-50/70 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-700/80">
            <div class="flex items-center gap-2.5 flex-wrap">
                <button type="button"
                        class="flex items-center gap-2 text-left cursor-pointer md:cursor-default focus:outline-none"
                        @click="if (!isDesktop) mobileFilterOpen = !mobileFilterOpen">
                    <div class="w-6 h-6 rounded-lg bg-sky-100 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0 border border-sky-200/80 dark:border-sky-800/60">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200 select-none">
                        Filter &amp; Pencarian
                    </span>
                </button>

                <span x-show="search.trim() !== ''" x-cloak
                      class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span>
                    <span>Pencarian Aktif</span>
                </span>
            </div>

            <!-- Mobile Toggle Button (Visible on Mobile only) -->
            <button type="button"
                    @click="mobileFilterOpen = !mobileFilterOpen"
                    :aria-expanded="mobileFilterOpen ? 'true' : 'false'"
                    class="md:hidden inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-white dark:bg-slate-700/80 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-600 transition-all cursor-pointer shadow-2xs">
                <span x-text="mobileFilterOpen ? 'Tutup Filter' : 'Buka Filter'"></span>
                <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200"
                     :class="mobileFilterOpen ? 'rotate-180' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                </svg>
            </button>
        </div>

        <!-- Collapsible Search Body -->
        <div x-show="isDesktop || mobileFilterOpen" x-collapse x-cloak class="p-4 sm:p-5 bg-white dark:bg-slate-800">
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1.5">
                Cari Paket
            </label>
            <div class="relative max-w-md">
                <input type="text"
                       x-model="search"
                       placeholder="Nama paket / kode paket..."
                       class="w-full h-9 pl-9 pr-7 text-xs border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <button type="button"
                        x-show="search"
                        @click="search = ''"
                        class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 cursor-pointer"
                        style="display: none;">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ── LAYER 3: FORM PAKET (CARD BUDGET = 1) ── --}}
    <form action="{{ route('business-development.package-restrictions.update') }}" method="POST" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-4 sm:p-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
            @forelse($packages as $package)
                @php
                    $pkgPayload = ['name' => $package->name, 'code' => $package->package_code];
                @endphp
                <label x-show="matches({{ json_encode($pkgPayload) }})"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-900/30 cursor-pointer">
                    <input type="checkbox" name="package_ids[]" value="{{ $package->id }}"
                           {{ in_array($package->id, $selectedIds, true) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                    <span class="text-sm text-slate-700 dark:text-slate-200">
                        {{ $package->name }}
                        <span class="block text-[10px] text-slate-400 dark:text-slate-500">{{ $package->package_code }} — {{ $package->monthly_price_formatted }}</span>
                    </span>
                </label>
            @empty
                <p class="text-sm text-slate-400 dark:text-slate-500 col-span-full">Belum ada paket aktif.</p>
            @endforelse
        </div>

        <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition-colors">
            Simpan Daftar Restriksi
        </button>
    </form>
</div>
@endsection
