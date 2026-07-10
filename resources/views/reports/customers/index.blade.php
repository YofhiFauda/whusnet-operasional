@extends('layouts.app')

@section('title', 'Laporan Pelanggan - Whusnet Operasional')
@section('page_title', 'Laporan Pelanggan')

@section('content')
@php
    $completenessBadges = [
        'draft' => 'bg-slate-50 text-slate-600 border-slate-200',
        'perlu_dilengkapi' => 'bg-amber-50 text-amber-700 border-amber-200',
        'lengkap' => 'bg-green-50 text-green-700 border-green-200',
        'siap_billing' => 'bg-sky-50 text-sky-700 border-sky-200',
    ];
    $completenessLabels = [
        'draft' => 'Draft',
        'perlu_dilengkapi' => 'Perlu Dilengkapi',
        'lengkap' => 'Lengkap',
        'siap_billing' => 'Siap Billing',
    ];
@endphp

<div class="space-y-6">
    <!-- Filter Card -->
    <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm">
        <form method="GET" action="{{ route('reports.customers.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Filter POP -->
                <div>
                    <label for="pop_id" class="block text-xs font-semibold text-slate-600 mb-1">POP / Cabang</label>
                    <select id="pop_id" name="pop_id" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Semua POP Akses</option>
                        @foreach($pops as $pop)
                            <option value="{{ $pop->id }}" @selected((string)$popId === (string)$pop->id)>
                                {{ $pop->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Kelengkapan Data -->
                <div>
                    <label for="completeness_status" class="block text-xs font-semibold text-slate-600 mb-1">Kelengkapan Data</label>
                    <select id="completeness_status" name="completeness_status" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Semua Status</option>
                        @foreach($completenessLabels as $key => $label)
                            <option value="{{ $key }}" @selected($completenessStatus === $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Status Pelanggan -->
                <div>
                    <label for="status" class="block text-xs font-semibold text-slate-600 mb-1">Status Pelanggan</label>
                    <select id="status" name="status" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                        <option value="">Semua Status</option>
                        @foreach($statuses as $item)
                            <option value="{{ $item->code }}" @selected($status === $item->code)>
                                {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Tanggal Registrasi Dari -->
                <div>
                    <label for="start_date" class="block text-xs font-semibold text-slate-600 mb-1">Registrasi Dari</label>
                    <input id="start_date" type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>

                <!-- Filter Tanggal Registrasi Sampai -->
                <div>
                    <label for="end_date" class="block text-xs font-semibold text-slate-600 mb-1">Registrasi Sampai</label>
                    <input id="end_date" type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row justify-between items-center pt-2 border-t border-slate-100 gap-3">
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filter Laporan
                    </button>
                    <a href="{{ route('reports.customers.index') }}" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm focus:outline-none">
                        Reset
                    </a>
                </div>

                <div class="w-full sm:w-auto">
                    <a href="{{ route('reports.customers.export', request()->query()) }}" class="w-full sm:w-auto inline-flex justify-center items-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                        <svg class="mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export Excel/CSV
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Data Summary & Table -->
    <div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
        <!-- Table Header Info -->
        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Daftar Pelanggan</h3>
            <span class="inline-flex items-center rounded-md bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800">
                Total: {{ $customers->total() }} Pelanggan
            </span>
        </div>

        <!-- Table View -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead>
                    <tr class="text-left text-xs font-bold uppercase tracking-wider text-slate-500 bg-slate-50 border-b border-slate-200">
                        <th class="px-6 py-3">Kode Pelanggan</th>
                        <th class="px-6 py-3">Nama Pelanggan</th>
                        <th class="px-6 py-3">POP / Cabang</th>
                        <th class="px-6 py-3">Paket Internet</th>
                        <th class="px-6 py-3">Kelengkapan</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Tgl Registrasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($customers as $customer)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-slate-800 whitespace-nowrap">
                                {{ $customer->customer_code ?? '-' }}
                                @if($customer->old_customer_id)
                                    <div class="text-[10px] font-normal text-slate-400">Lama: {{ $customer->old_customer_id }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-bold text-slate-900">{{ $customer->full_name }}</div>
                                <div class="text-xs text-slate-500">{{ $customer->primary_phone ?? $customer->phone }}</div>
                            </td>
                            <td class="px-6 py-4 text-slate-700 whitespace-nowrap">
                                {{ $customer->pop->name ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($customer->internetPackage)
                                    <div class="font-medium text-slate-800">{{ $customer->internetPackage->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $customer->internetPackage->download_speed_mbps }} Mbps</div>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $completenessBadges[$customer->data_completeness_status] ?? 'bg-slate-50 text-slate-600 border-slate-200' }}">
                                    {{ $completenessLabels[$customer->data_completeness_status] ?? $customer->data_completeness_status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $customer->subscriptionStatus ? $customer->subscriptionStatus->badgeClasses() : 'bg-slate-50 text-slate-700 border-slate-100' }}">
                                    {{ $customer->subscriptionStatus->name ?? ucfirst(str_replace('_', ' ', $customer->status)) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-slate-600 whitespace-nowrap">
                                {{ $customer->registration_date ? $customer->registration_date->format('d/m/Y') : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-center text-slate-500">
                                <svg class="mx-auto h-12 w-12 text-slate-400 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                                Tidak ada data pelanggan yang cocok dengan kriteria filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination Footer -->
        @if($customers->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
