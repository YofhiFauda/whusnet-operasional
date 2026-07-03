@extends('layouts.app')

@section('title', 'Central List Task')

@section('content')
<div class="px-6 py-5 max-w-7xl mx-auto space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-text-main">Central List Task</h1>
            <p class="text-sm text-text-muted mt-1">Daftar lengkap seluruh tiket/task beserta statusnya.</p>
        </div>
        @can('create', \App\Models\Task::class)
        <a href="{{ route('tasks.create') }}"
           class="inline-flex items-center gap-1.5 bg-primary hover:bg-primary-hover text-white text-sm font-semibold px-4 py-2 rounded-md transition-colors shrink-0">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Buat Task Baru
        </a>
        @endcan
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('tasks.index') }}" class="bg-surface border border-border rounded-lg p-4 flex flex-col gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-widest mb-1.5">Pencarian</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="No task / pelanggan..." class="w-full text-sm border border-border rounded bg-background px-3 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-widest mb-1.5">Status</label>
                <select name="status" class="w-full text-sm border border-border rounded bg-background px-3 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <option value="">Semua Status Aktif</option>
                    <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>Semua Status (Termasuk Selesai & Batal)</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" {{ request('status') === $status->value ? 'selected' : '' }}>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-widest mb-1.5">Tipe</label>
                <select name="type" class="w-full text-sm border border-border rounded bg-background px-3 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <option value="">Semua Tipe</option>
                    @foreach($types as $type)
                        <option value="{{ $type->value }}" {{ request('type') === $type->value ? 'selected' : '' }}>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-widest mb-1.5">Dari Tgl</label>
                <input type="date" name="date_start" value="{{ request('date_start') }}" class="w-full text-sm border border-border rounded bg-background px-3 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-text-muted uppercase tracking-widest mb-1.5">Urutkan</label>
                <select name="sort" class="w-full text-sm border border-border rounded bg-background px-3 py-1.5 focus:border-primary focus:ring-1 focus:ring-primary outline-none">
                    <option value="date_desc" {{ request('sort') === 'date_desc' ? 'selected' : '' }}>Tanggal Terjadwal (Terbaru)</option>
                    <option value="date_asc" {{ request('sort') === 'date_asc' ? 'selected' : '' }}>Tanggal Terjadwal (Terlama)</option>
                    <option value="status" {{ request('sort') === 'status' ? 'selected' : '' }}>Status</option>
                    <option value="type" {{ request('sort') === 'type' ? 'selected' : '' }}>Tipe Task</option>
                </select>
            </div>
        </div>
        <div class="flex items-center justify-end gap-2">
            @if(request()->anyFilled(['search', 'status', 'type', 'date_start', 'sort']))
                <a href="{{ route('tasks.index') }}" class="text-xs font-semibold text-text-muted hover:text-text-main">Reset Filter</a>
            @endif
            <button type="submit" class="bg-surface-muted border border-border text-text-main text-xs font-semibold px-4 py-1.5 rounded hover:bg-border transition-colors">Terapkan Filter</button>
        </div>
    </form>

    {{-- Task List --}}
    <div class="space-y-3">
        @forelse($tasks as $task)
            <x-task-card :task="$task" displayMode="list" :showCountdown="true" />
        @empty
            <div class="bg-surface border border-border rounded-lg p-10 flex flex-col items-center justify-center text-text-muted">
                <svg class="w-12 h-12 mb-3 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="text-sm font-medium">Tidak ada task yang ditemukan.</p>
                @if(request()->anyFilled(['search', 'status', 'type', 'date_start']))
                    <p class="text-xs mt-1">Coba sesuaikan filter pencarian Anda.</p>
                @endif
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($tasks->hasPages())
        <div class="pt-4">
            {{ $tasks->links() }}
        </div>
    @endif

</div>
@endsection
