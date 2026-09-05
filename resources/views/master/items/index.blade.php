@extends('layouts.app')

@section('title', 'Master Barang & Material - Whusnet Operasional')
@section('page_title', 'Master Barang/Material')

@section('content')

<x-warehouse.header active="items" title="Master Katalog Barang" subtitle="Daftar referensi material instalasi, perangkat aktif (ONT/Router), kabel feeder, dan aksesoris pasif ISP." />

<!-- Controls & Filters Panel -->
<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 mb-6 shadow-xs">
    <form action="{{ route('master.items.index') }}" method="GET" class="space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <!-- Search -->
            <div class="md:col-span-4">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Pencarian Barang</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Ketik kode atau nama barang..."
                           class="w-full pl-9 pr-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Filter Kategori -->
            <div class="md:col-span-3">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Kategori Barang</label>
                <select name="type" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <option value="">— Semua Kategori —</option>
                    @foreach($categories as $category)
                    <option value="{{ $category->code }}" {{ $type === $category->code ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Status -->
            <div class="md:col-span-2">
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Status</label>
                <select name="status" class="w-full px-3 py-2 text-xs font-medium border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                    <option value="">Semua Status</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="md:col-span-3 flex items-center gap-2 justify-end">
                <button type="submit" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-semibold rounded-xl shadow-xs transition-colors cursor-pointer">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span>Filter</span>
                </button>

                @if($search || $type || $status)
                <a href="{{ route('master.items.index') }}" class="inline-flex items-center justify-center p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700/50 transition-colors" title="Reset Filter">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.28 15m-2.802-5.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                    </svg>
                </a>
                @endif

                @if(auth()->user()->hasPermission('items.create'))
                <a href="{{ route('master.items.create') }}" class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-xl text-white bg-sky-600 hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-600 shadow-xs shadow-sky-600/20 transition-all cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    <span>Tambah Barang</span>
                </a>
                @endif
            </div>
        </div>
    </form>
</div>

<!-- Table List -->
<div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
    @if($items->isEmpty())
    <div class="p-16 text-center">
        <div class="w-14 h-14 mx-auto bg-slate-100 dark:bg-slate-700/50 rounded-2xl flex items-center justify-center mb-3 text-slate-400">
            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
        </div>
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada barang ditemukan</h4>
        <p class="text-xs text-slate-400 max-w-sm mx-auto mt-1">
            @if($search || $type || $status)
            Silakan ubah atau reset parameter pencarian filter Anda.
            @else
            Mulai daftarkan barang baru ke dalam katalog sistem.
            @endif
        </p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-16">No</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kode &amp; Nama Barang</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kategori</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Metode Tracking</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Satuan</th>
                    <th scope="col" class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                    @if(auth()->user()->hasPermission('items.update'))
                    <th scope="col" class="px-6 py-3.5 text-center text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-24">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($items as $index => $item)
                @php
                    $tracking = $item->tracking_type->value ?? 'quantity';
                @endphp
                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-400 font-mono">
                        {{ $items->firstItem() + $index }}
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $item->name }}</div>
                        <div class="text-xs font-mono text-slate-400 mt-0.5">{{ $item->code }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2.5 py-0.5 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                            {{ $item->category?->name ?? '-' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($tracking === 'serialized')
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-cyan-50 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800">
                            <span>SERIAL NUMBER</span>
                        </span>
                        @elseif($tracking === 'batch')
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-purple-50 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800">
                            <span>BATCH / LOT</span>
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                            <span>QUANTITY</span>
                        </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-xs font-mono font-bold text-slate-700 dark:text-slate-300">{{ $item->unit }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($item->is_active)
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-bold rounded-full bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">Aktif</span>
                        @else
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">Nonaktif</span>
                        @endif
                    </td>
                    @if(auth()->user()->hasPermission('items.update'))
                    <td class="px-6 py-4 whitespace-nowrap text-center text-xs font-medium">
                        <div class="flex items-center justify-center gap-1.5">
                            <a href="{{ route('master.items.edit', $item) }}" class="p-1.5 text-slate-400 dark:text-slate-500 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-900/20 rounded-lg transition-colors" title="Ubah Barang">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </a>

                            <form action="{{ route('master.items.toggle', $item) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                        class="p-1.5 text-slate-400 dark:text-slate-500 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-colors cursor-pointer"
                                        title="{{ $item->is_active ? 'Nonaktifkan' : 'Aktifkan' }} Barang"
                                        onclick="event.preventDefault(); window.confirmDelete('Apakah Anda yakin ingin {{ $item->is_active ? 'menonaktifkan' : 'mengaktifkan' }} barang {{ $item->name }}?', this.closest('form'))">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40">
        {{ $items->links() }}
    </div>
    @endif
    @endif
</div>
@endsection
