@extends('layouts.app')

@section('title', 'Master Barang & Material - Whusnet Operasional')
@section('page_title', 'Master Barang/Material')

@section('content')

<x-warehouse.header active="items" title="Master Katalog Barang" subtitle="Daftar referensi material instalasi, perangkat aktif (ONT/Router), kabel feeder, dan aksesoris pasif ISP." />

<div class="space-y-5">
    <!-- Layer 2: Flat Summary Strip (Satu strip datar dengan divider vertikal) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 bg-surface border border-border rounded-lg divide-y sm:divide-y-0 sm:divide-x divide-border overflow-hidden">
        <!-- Col 1: Total Katalog -->
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">TOTAL SKU KATALOG</span>
                <span class="text-sky-600 dark:text-sky-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-text-main tabular-nums">{{ \App\Models\Item::count() }}</span>
                <span class="text-[11px] text-text-muted">Item SKU</span>
            </div>
        </div>

        <!-- Col 2: Barang Aktif -->
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">STATUS AKTIF</span>
                <span class="text-emerald-600 dark:text-emerald-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 tabular-nums">{{ \App\Models\Item::where('is_active', true)->count() }}</span>
                <span class="text-[11px] text-text-muted">Aktif</span>
            </div>
        </div>

        <!-- Col 3: Serialized -->
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">SERIAL NUMBER (SN)</span>
                <span class="text-cyan-600 dark:text-cyan-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 10h.01M7 13h.01M10 7h.01M10 10h.01M10 13h.01M14 7h.01M14 10h.01M14 13h.01M17 7h.01M17 10h.01M17 13h.01M3 21h18a2 2 0 002-2V5a2 2 0 00-2-2H3a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-cyan-600 dark:text-cyan-400 tabular-nums">{{ \App\Models\Item::where('tracking_type', 'serialized')->count() }}</span>
                <span class="text-[11px] text-text-muted">Perangkat</span>
            </div>
        </div>

        <!-- Col 4: Roll Kabel -->
        <div class="p-3.5 sm:p-4 flex flex-col justify-between gap-1">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-semibold text-text-muted uppercase tracking-wider">KABEL ROLL</span>
                <span class="text-amber-600 dark:text-amber-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
            </div>
            <div class="flex items-baseline justify-between mt-1">
                <span class="text-xl sm:text-2xl font-bold font-mono text-amber-600 dark:text-amber-400 tabular-nums">{{ \App\Models\Item::where('tracking_type', 'roll')->count() }}</span>
                <span class="text-[11px] text-text-muted">Kabel FO</span>
            </div>
        </div>
    </div>

    <!-- Layer 2.5: Naked Filter Bar (Filter Bar tanpa pembungkus card) -->
    <form action="{{ route('master.items.index') }}" method="GET" class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <!-- Search -->
            <div class="md:col-span-5">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Pencarian Barang</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Ketik kode atau nama barang..."
                           class="w-full pl-9 pr-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main placeholder:text-text-muted focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-text-muted">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Filter Kategori -->
            <div class="md:col-span-3">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Kategori Barang</label>
                <select name="type" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors cursor-pointer">
                    <option value="">— Semua Kategori —</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->code }}" {{ $type === $category->code ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Status -->
            <div class="md:col-span-2">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 text-xs border border-border rounded-lg bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors cursor-pointer">
                    <option value="">Semua Status</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="md:col-span-2 flex items-center gap-2 justify-end">
                <button type="submit" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-3.5 py-2 bg-primary hover:bg-primary-hover text-white text-xs font-semibold rounded-lg shadow-xs transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                </button>

                @if($search || $type || $status)
                <a href="{{ route('master.items.index') }}" class="inline-flex items-center justify-center p-2 text-text-secondary hover:text-text-main rounded-lg border border-border hover:bg-surface-muted transition-colors" title="Reset Filter">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.28 15m-2.802-5.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                    </svg>
                </a>
                @endif

                @if(auth()->user()->hasPermission('items.create'))
                <a href="{{ route('master.items.create') }}" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg text-white bg-sky-600 hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-600 shadow-xs transition-colors cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Tambah</span>
                </a>
                @endif
            </div>
        </div>
    </form>

    <!-- Layer 3 & 4: Table Panel & Pagination (Card Budget = 1, Satu Panel untuk Tabel & Paginasi) -->
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-xs">
        @if($items->isEmpty())
        <div class="p-16 text-center">
            <div class="w-14 h-14 mx-auto bg-surface-muted rounded-full flex items-center justify-center mb-3 text-text-muted">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
            <h4 class="text-sm font-bold text-text-main">Tidak ada barang ditemukan</h4>
            <p class="text-xs text-text-muted max-w-sm mx-auto mt-1">
                @if($search || $type || $status)
                Silakan ubah atau reset parameter pencarian filter Anda.
                @else
                Mulai daftarkan barang baru ke dalam katalog sistem.
                @endif
            </p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-left text-xs text-text-secondary">
                <thead>
                    <tr class="bg-surface-muted/60 border-b border-border text-text-muted font-bold text-[10px] uppercase tracking-wider">
                        <th scope="col" class="px-5 py-3 text-left w-14 font-mono">No</th>
                        <th scope="col" class="px-5 py-3 text-left">Kode &amp; Nama Barang</th>
                        <th scope="col" class="px-5 py-3 text-left">Kategori</th>
                        <th scope="col" class="px-5 py-3 text-left">Metode Tracking</th>
                        <th scope="col" class="px-5 py-3 text-left">Satuan</th>
                        <th scope="col" class="px-5 py-3 text-left">Status</th>
                        @if(auth()->user()->hasPermission('items.update'))
                        <th scope="col" class="px-5 py-3 text-center w-24">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($items as $index => $item)
                    @php
                        $tracking = $item->tracking_type->value ?? 'quantity';
                    @endphp
                    <tr class="hover:bg-surface-muted/50 transition-colors">
                        <td class="px-5 py-3.5 whitespace-nowrap text-xs text-text-muted font-mono">
                            {{ $items->firstItem() + $index }}
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="text-xs font-bold text-text-main">{{ $item->name }}</div>
                            <div class="text-[11px] font-mono text-text-muted mt-0.5">{{ $item->code }}</div>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-medium bg-surface-muted text-text-secondary border border-border">
                                {{ $item->category?->name ?? '-' }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            @if($tracking === 'serialized')
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-cyan-50 dark:bg-cyan-950/50 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800">
                                <span>SERIAL NUMBER</span>
                            </span>
                            @elseif($tracking === 'roll')
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                <span>ROLL KABEL</span>
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-border">
                                <span>QUANTITY</span>
                            </span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            <span class="text-xs font-mono font-bold text-text-main">{{ $item->unit }}</span>
                        </td>
                        <td class="px-5 py-3.5 whitespace-nowrap">
                            @if($item->is_active)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-bold rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Aktif
                            </span>
                            @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-slate-100 dark:bg-slate-800 text-text-muted border border-border">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                Nonaktif
                            </span>
                            @endif
                        </td>
                        @if(auth()->user()->hasPermission('items.update'))
                        <td class="px-5 py-3.5 whitespace-nowrap text-center text-xs font-medium">
                            <div class="flex items-center justify-center gap-1.5">
                                <a href="{{ route('master.items.edit', $item) }}" class="p-1.5 text-text-muted hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="Ubah Barang">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </a>

                                @if(auth()->user()->hasPermission('items.delete'))
                                <form action="{{ route('master.items.destroy', $item) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="p-1.5 text-text-muted hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-lg transition-colors cursor-pointer"
                                            title="Hapus Barang"
                                            onclick="event.preventDefault(); window.confirmDelete(@js('Hapus barang '.($item->name).'? Jika sudah punya riwayat stok/transaksi, data hanya dinonaktifkan.'), this.closest('form'))">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </form>
                                @endif
                                <form action="{{ route('master.items.toggle', $item) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit"
                                            class="p-1.5 text-text-muted hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 rounded-lg transition-colors cursor-pointer"
                                            title="{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }} Barang"
                                            onclick="event.preventDefault(); window.confirmDelete(@js('Apakah Anda yakin ingin '.($item->is_active ? 'menonaktifkan' : 'mengaktifkan').' barang '.($item->name).'?'), this.closest('form'))">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($items->hasPages())
        <div class="px-5 py-3 border-t border-border bg-surface-muted/30">
            {{ $items->links() }}
        </div>
        @endif
        @endif
    </div>
</div>
@endsection
