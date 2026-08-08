@extends('layouts.app')

@section('title', 'Master Issue - Whusnet Operasional')
@section('page_title', 'Master Issue/Kategori Keluhan')

@section('content')
{{-- Notification Alerts handled by global Component Toast (<x-toast/>) --}}

<!-- Header Stats Bar -->
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6 shadow-sm">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">Master Issue/Kategori Keluhan</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Kelola kategori keluhan untuk dropdown "Kategori Issue" di form Buat Tiket Layanan. Data awal masih contoh — wajib direview sebelum go-live.</p>
        </div>
        <div class="flex flex-wrap items-center gap-4 md:gap-6">
            <div class="text-center">
                <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">TOTAL KATEGORI</span>
                <span class="text-lg font-bold text-slate-800 dark:text-slate-200 data-text">{{ \App\Models\TicketIssueCategory::count() }}</span>
            </div>
        </div>
    </div>
</div>

<!-- Controls Panel -->
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 mb-6 shadow-sm">
    <form action="{{ route('master.ticket-issue-categories.index') }}" method="GET" class="flex flex-col lg:flex-row items-end lg:items-center justify-between gap-4">
        <!-- Filters Area -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full lg:max-w-2xl">
            <!-- Search -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Pencarian</label>
                <div class="relative">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Nama kategori..."
                           class="w-full pl-9 pr-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Filter Status -->
            <div>
                <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1.5">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 bg-white dark:bg-slate-800">
                    <option value="">Semua Status</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
        </div>

        <!-- Buttons Area -->
        <div class="flex items-center gap-2 w-full lg:w-auto shrink-0 justify-end">
            <button type="submit" class="flex-1 lg:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50 transition-colors focus:outline-none cursor-pointer">
                <svg class="h-4 w-4 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                Filter
            </button>

            @if($search || $status)
            <a href="{{ route('master.ticket-issue-categories.index') }}" class="inline-flex items-center justify-center p-2 border border-slate-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50 transition-colors focus:outline-none cursor-pointer" title="Reset Filters">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.28 15m-2.802-5.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
                </svg>
            </a>
            @endif

            @if(auth()->user()->hasPermission('ticket_issue_categories.create'))
            <a href="{{ route('master.ticket-issue-categories.create') }}" class="flex-1 lg:flex-none inline-flex items-center justify-center gap-2 px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 dark:bg-sky-500 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Kategori
            </a>
            @endif
        </div>
    </form>
</div>

<!-- Table / Cards List -->
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-sm">
    @if($categories->isEmpty())
    <div class="p-16 text-center">
        <div class="h-16 w-16 mx-auto bg-slate-100 dark:bg-slate-700/50 rounded-full flex items-center justify-center mb-4 text-slate-400 dark:text-slate-500">
            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5" />
            </svg>
        </div>
        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Tidak ada Kategori Issue ditemukan</h4>
        <p class="text-xs text-slate-400 dark:text-slate-500 max-w-sm mx-auto mt-1">
            @if($search || $status)
            Silakan reset filter pencarian atau ubah parameter filter Anda.
            @else
            Mulai dengan menambahkan kategori issue baru menggunakan tombol Tambah Kategori.
            @endif
        </p>
    </div>
    @else
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-16">No</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Nama Kategori</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Prioritas Default</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sumber SLA</th>
                    <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                    @if(auth()->user()->hasPermission('ticket_issue_categories.update'))
                    <th scope="col" class="px-6 py-3 text-center text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-24">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach($categories as $index => $category)
                <tr class="hover:bg-slate-50/50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                        {{ $categories->firstItem() + $index }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $category->name }}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm text-slate-600 dark:text-slate-400">{{ $category->default_priority?->value }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm text-slate-600 dark:text-slate-400">{{ $category->sla_source }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($category->is_active)
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">Aktif</span>
                        @else
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">Nonaktif</span>
                        @endif
                    </td>
                    @if(auth()->user()->hasPermission('ticket_issue_categories.update'))
                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                        <div class="flex items-center justify-center gap-2">
                            <!-- Edit -->
                            <a href="{{ route('master.ticket-issue-categories.edit', $category) }}" class="p-1 text-slate-400 dark:text-slate-500 hover:text-sky-600 hover:bg-sky-50 dark:hover:bg-sky-900/20 rounded-md transition-colors" title="Ubah Kategori">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </a>

                            <!-- Toggle Status (Form) -->
                            <form action="{{ route('master.ticket-issue-categories.toggle', $category) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit"
                                        class="p-1 text-slate-400 dark:text-slate-500 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-md transition-colors cursor-pointer"
                                        title="{{ $category->is_active ? 'Nonaktifkan' : 'Aktifkan' }} Kategori"
                                        onclick="event.preventDefault(); window.confirmDelete('Apakah Anda yakin ingin {{ $category->is_active ? 'menonaktifkan' : 'mengaktifkan' }} kategori {{ $category->name }}?', this.closest('form'))">
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
    @if($categories->hasPages())
    <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700/50 bg-slate-50/50">
        {{ $categories->links() }}
    </div>
    @endif
    @endif
</div>
@endsection
