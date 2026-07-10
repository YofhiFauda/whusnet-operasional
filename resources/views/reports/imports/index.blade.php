@extends('layouts.app')

@section('title', 'Laporan Import Data - Whusnet Operasional')
@section('page_title', 'Laporan Import Data')

@section('content')
<div class="space-y-6">
    <!-- Metrik Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Batches -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Batch Import</p>
                <h3 class="text-2xl font-bold font-mono text-slate-800">{{ number_format($totalBatchesCount) }}</h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
        </div>

        <!-- Total Rows Attempted -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Baris Diproses</p>
                <h3 class="text-2xl font-bold font-mono text-slate-800">{{ number_format($totalRowsSum) }}</h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-slate-50 text-slate-600 flex items-center justify-center">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </div>
        </div>

        <!-- Total Success Imported -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Berhasil Diimport</p>
                <h3 class="text-2xl font-bold font-mono text-green-600">{{ number_format($totalImportedSum) }}</h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-green-50 text-green-600 flex items-center justify-center">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>

        <!-- Total Invalid/Errors -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm flex items-center justify-between">
            <div class="space-y-1">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Gagal / Error</p>
                <h3 class="text-2xl font-bold font-mono text-red-600">{{ number_format($totalInvalidSum) }}</h3>
            </div>
            <div class="h-12 w-12 rounded-lg bg-red-50 text-red-600 flex items-center justify-center">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm">
        <form method="GET" action="{{ route('reports.imports.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Search Batch / File -->
                <div>
                    <label for="search" class="block text-xs font-semibold text-slate-600 mb-1">Cari Batch / File</label>
                    <input id="search" type="text" name="search" value="{{ $search }}" placeholder="No. Batch atau Nama File" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <!-- Filter Status -->
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select id="status" name="status" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Semua Status</option>
                        @foreach($allowedStatuses as $item)
                            <option value="{{ $item }}" @selected($status === $item)>
                                {{ strtoupper($item) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Tanggal Dari -->
                <div>
                    <label for="start_date" class="block text-xs font-semibold text-slate-600 mb-1">Dari Tanggal</label>
                    <input id="start_date" type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <!-- Filter Tanggal Sampai -->
                <div>
                    <label for="end_date" class="block text-xs font-semibold text-slate-600 mb-1">Sampai Tanggal</label>
                    <input id="end_date" type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row justify-start items-center pt-2 border-t border-slate-100 gap-3">
                <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                    <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filter Laporan
                </button>
                <a href="{{ route('reports.imports.index') }}" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm focus:outline-none">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
        <!-- Table Header Info -->
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Daftar Batch Import</h3>
            <span class="inline-flex items-center rounded-md bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800">
                Total: {{ $batches->total() }} Batch
            </span>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500 bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-3">Waktu Upload</th>
                        <th class="px-6 py-3">Nomor Batch</th>
                        <th class="px-6 py-3">Nama File / Sumber</th>
                        <th class="px-6 py-3">Petugas</th>
                        <th class="px-6 py-3 text-center">Total Baris</th>
                        <th class="px-6 py-3 text-center">Valid / Error</th>
                        <th class="px-6 py-3 text-center">Berhasil Import</th>
                        <th class="px-6 py-3 text-center">Status</th>
                        <th class="px-6 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($batches as $batch)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 text-slate-600 whitespace-nowrap">
                                {{ $batch->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-6 py-4 font-mono font-bold text-slate-800 whitespace-nowrap">
                                {{ $batch->batch_number }}
                            </td>
                            <td class="px-6 py-4 text-slate-700 max-w-xs truncate whitespace-nowrap" title="{{ $batch->file_name }}">
                                {{ $batch->file_name }}
                            </td>
                            <td class="px-6 py-4 text-slate-700 whitespace-nowrap">
                                {{ $batch->user?->name ?? 'System' }}
                            </td>
                            <td class="px-6 py-4 text-center font-mono font-bold text-slate-800">
                                {{ number_format($batch->total_rows) }}
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <span class="text-green-600 font-bold font-mono">{{ number_format($batch->valid_rows) }}</span>
                                <span class="text-slate-300 mx-1">/</span>
                                <span class="text-red-600 font-bold font-mono">{{ number_format($batch->invalid_rows) }}</span>
                            </td>
                            <td class="px-6 py-4 text-center font-mono font-bold text-sky-600">
                                {{ number_format($batch->imported_rows) }}
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                @if($batch->status === 'imported')
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700 border border-green-200">IMPORTED</span>
                                @elseif($batch->status === 'failed')
                                    <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700 border border-red-200">FAILED</span>
                                @elseif($batch->status === 'pending')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 border border-amber-200">PENDING</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-50 px-2.5 py-0.5 text-xs font-semibold text-slate-700 border border-slate-200 uppercase">{{ $batch->status }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center whitespace-nowrap">
                                <a href="{{ route('reports.imports.show', $batch->id) }}" class="inline-flex items-center justify-center rounded border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 hover:text-slate-900 transition-colors">
                                    Detail Log
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-10 text-center text-slate-500">
                                <svg class="mx-auto h-12 w-12 text-slate-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                Tidak ada data batch import yang cocok dengan kriteria filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        @if($batches->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50">
                {{ $batches->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
