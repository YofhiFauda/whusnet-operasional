@extends('layouts.app')

@section('title', 'Master Distribusi - Whusnet Operasional')
@section('page_title', 'Master Distribusi')

@section('content')
<!-- Notification Alerts handled by global Component Toast (<x-toast />) -->

<!-- Header Stats Bar -->
<div class="bg-white border border-slate-200 rounded-lg p-6 mb-6 shadow-sm">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-bold text-slate-800">Manajemen Distribusi / Wilayah Cabang</h3>
            <p class="text-xs text-slate-500 mt-0.5">Kelola data distribusi area yang berada di bawah naungan POP / Cabang.</p>
        </div>
        <div class="flex flex-wrap items-center gap-4 md:gap-6">
            <div class="text-center">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">TOTAL DISTRIBUSI</span>
                <span class="text-lg font-bold text-slate-800 data-text">{{ \App\Models\Distribution::count() }}</span>
            </div>
        </div>
    </div>
</div>

<!-- Controls Panel -->
<div class="bg-white border border-slate-200 rounded-lg p-5 mb-6 shadow-sm">
    <form action="{{ route('master.distribusi.index') }}" method="GET" class="flex flex-col lg:flex-row items-end lg:items-center justify-between gap-4">
        <!-- Filters Area -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full lg:max-w-2xl">
            <!-- Search -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Pencarian</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Kode, Nama, atau Deskripsi..."
                           class="w-full pl-9 pr-3 py-2 border border-slate-300 rounded-md text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Filter POP -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Cabang / POP</label>
                <select name="pop_id" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 bg-white">
                    <option value="">Semua POP</option>
                    @foreach($pops as $pop)
                        <option value="{{ $pop->id }}" {{ (int) $pop_id === $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Buttons Area -->
        <div class="flex items-center gap-2 w-full lg:w-auto shrink-0 justify-end">
            <button type="submit" class="flex-1 lg:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 border border-slate-300 rounded-md shadow-sm text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors focus:outline-none cursor-pointer">
                <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                Filter
            </button>

            @if($search || $pop_id)
            <a href="{{ route('master.distribusi.index') }}" class="inline-flex items-center justify-center p-2 border border-slate-300 rounded-md shadow-sm text-sm font-medium text-slate-500 bg-white hover:bg-slate-50 transition-colors focus:outline-none cursor-pointer" title="Reset Filters">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.28 15m-2.802-5.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                </svg>
            </a>
            @endif

            @if(auth()->user()->hasPermission('manage_pop'))
            <a href="{{ route('master.distribusi.create') }}" class="flex-1 lg:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Distribusi
            </a>
            @endif
        </div>
    </form>
</div>

<!-- Table / Cards List -->
<div class="bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
    @if($distributions->isEmpty())
    <div class="p-16 text-center">
        <div class="h-16 w-16 mx-auto bg-slate-100 rounded-full flex items-center justify-center mb-4 text-slate-400">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
        </div>
        <h4 class="text-sm font-bold text-slate-800">Tidak ada Distribusi ditemukan</h4>
        <p class="text-xs text-slate-400 max-w-sm mx-auto mt-1">
            @if($search || $pop_id)
            Silakan reset filter pencarian atau ubah parameter filter Anda.
            @else
            Mulai dengan menambahkan distribusi baru menggunakan tombol Tambah Distribusi.
            @endif
        </p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-16">No</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Kode</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Nama Distribusi</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Deskripsi</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Cabang</th>
                    @if(auth()->user()->hasPermission('manage_pop'))
                    <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-slate-500 uppercase tracking-wider w-24">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-slate-100">
                @foreach($distributions as $index => $distribusi)
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                        {{ $distributions->firstItem() + $index }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-slate-900 tracking-tight">
                        {{ $distribusi->code }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-semibold text-slate-800">{{ $distribusi->name }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-slate-600">{{ $distribusi->description }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($distribusi->pop)
                            <div class="text-sm font-semibold text-slate-800">{{ $distribusi->pop->name }}</div>
                            <span class="block text-[10px] text-slate-400 font-mono">{{ $distribusi->pop->code }}</span>
                        @else
                            <span class="text-slate-300">-</span>
                        @endif
                    </td>
                    @if(auth()->user()->hasPermission('manage_pop'))
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <div class="flex items-center justify-center gap-2">
                            <!-- Edit -->
                            <a href="{{ route('master.distribusi.edit', $distribusi) }}" class="p-1 text-slate-400 hover:text-sky-600 hover:bg-sky-50 rounded-md transition-colors" title="Ubah Distribusi">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </a>

                            <!-- Delete (Form) -->
                            <form action="{{ route('master.distribusi.destroy', $distribusi) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-md transition-colors cursor-pointer"
                                        title="Hapus Distribusi"
                                        onclick="event.preventDefault(); window.confirmDelete('Apakah Anda yakin ingin menghapus Distribusi {{ $distribusi->code }}?', this.closest('form'))">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
    @if($distributions->hasPages())
    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50">
        {{ $distributions->links() }}
    </div>
    @endif
    @endif
</div>
@endsection