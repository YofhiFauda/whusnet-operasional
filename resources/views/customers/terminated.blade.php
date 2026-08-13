@extends('layouts.app')

{{-- List Pelanggan Putus — halaman sendiri (route customers.terminated,
     permission customers.terminated.view, CustomerTerminatedController).
     Lihat catatan customers/failed.blade.php — alasan pemisahannya sama. --}}
@php
    $pageTitle = 'Pelanggan Putus';
@endphp

@section('title', $pageTitle . ' - Whusnet Operasional')
@section('page_title', $pageTitle)
@section('breadcrumb_parent', 'Pelanggan')
@section('breadcrumb_parent_url', '/customers')

@section('content')
@include('customers.partials._list_styles')
@include('customers.partials._list_header')
@include('customers.partials._list_stats')
@include('customers.partials._list_filters')

<div class="@container bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden mb-6">
    <div class="border-b border-slate-200 dark:border-slate-800 px-6 py-3.5 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/30">
        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Daftar Pelanggan Putus</span>
        <a href="{{ route('customers.index') }}" class="text-xs text-sky-600 dark:text-sky-400 hover:underline">Lihat Semua Pelanggan</a>
    </div>

    {{-- DESKTOP: Table layout, visible on container screens >= 64rem --}}
    <div class="hidden @min-[64rem]:block overflow-x-auto">
        <table class="w-full min-w-[1100px] border-collapse text-left text-xs" id="customerTable">
            <thead>
                <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    <th scope="col" class="py-3.5 px-5">CID</th>
                    <th scope="col" class="py-3.5 px-4">Nama Pelanggan</th>
                    <th scope="col" class="py-3.5 px-4">POP</th>
                    <th scope="col" class="py-3.5 px-4">Kontrak</th>
                    <th scope="col" class="py-3.5 px-4">Alasan Putus</th>
                    <th scope="col" class="py-3.5 px-4">Tgl Pemutusan</th>
                    <th scope="col" class="py-3.5 px-4 text-center">Status Alat</th>
                    <th scope="col" class="py-3.5 px-5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-slate-700 dark:text-slate-200">
                @forelse($customers as $customer)
                @php
                    $contractType = match($customer->customerService->contract_type ?? null) {
                        'sewa' => 'Sewa',
                        'beli' => 'Beli',
                        default => '-',
                    };
                    $isDeviceRetrieved = (bool) $customer->device_retrieved_at;
                @endphp
                <tr class="hover:bg-sky-50/40 dark:hover:bg-sky-950/20 transition-colors">
                    <td class="px-5 py-3.5 font-mono font-semibold text-sky-600 dark:text-sky-400 whitespace-nowrap">
                        {{ $customer->display_id }}
                    </td>
                    <td class="px-4 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ $customer->full_name }}
                    </td>
                    <td class="px-4 py-3.5 font-medium text-slate-700 dark:text-slate-300">
                        {{ $customer->pop->name ?? '-' }}
                    </td>
                    <td class="px-4 py-3.5 font-medium text-slate-600 dark:text-slate-400">
                        {{ $contractType }}
                    </td>
                    <td class="px-4 py-3.5 max-w-xs text-slate-600 dark:text-slate-400 truncate">
                        {{ $customer->termination_reason ?? '-' }}
                    </td>
                    <td class="px-4 py-3.5 font-mono text-slate-500 whitespace-nowrap">
                        {{ $customer->terminated_at ? \App\Support\IndonesianDate::date($customer->terminated_at) : '-' }}
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border {{ $isDeviceRetrieved ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800/60' : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800/60' }}">
                            {{ $isDeviceRetrieved ? 'Sudah Diambil' : 'Belum Diambil' }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                        <div class="inline-flex items-center gap-2">
                            <a href="{{ route('customers.show', $customer->id) }}"
                               class="px-2.5 py-1 text-xs font-medium text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800 rounded-lg hover:bg-sky-50 dark:hover:bg-sky-950/30 transition-colors">
                                Detail
                            </a>
                            @if(!$isDeviceRetrieved && auth()->user()->hasPermission('customers.detail.devices.retrieve'))
                            <form action="{{ route('customers.retrieve-device', $customer->id) }}" method="POST"
                                  onsubmit="event.preventDefault(); window.confirmAction('Buat Task FOP pengambilan alat untuk {{ $customer->full_name }}?', this);">
                                @csrf
                                <button type="submit" class="px-2.5 py-1 text-xs font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                                    Ambil Alat
                                </button>
                            </form>
                            @endif
                            @if(auth()->user()->hasPermission('customers.detail.installation.validate'))
                            <form action="{{ route('customers.reactivate', $customer->id) }}" method="POST"
                                  onsubmit="event.preventDefault(); window.confirmAction('Aktifkan kembali langganan {{ $customer->full_name }}?', this);">
                                @csrf
                                <button type="submit" class="px-2.5 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-950/30 transition-colors cursor-pointer">
                                    Langganan Lagi
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-slate-400">Tidak ada data pelanggan putus.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- MOBILE & TABLET: Card-based grid layout, visible on container screens < 64rem --}}
    <div class="@min-[64rem]:hidden p-4 bg-slate-50/40 dark:bg-slate-950/15 border-t border-slate-100 dark:border-slate-800/80">
        <div class="grid grid-cols-1 @min-[36rem]:grid-cols-2 gap-4">
            @forelse($customers as $customer)
            @php
                $contractType = match($customer->customerService->contract_type ?? null) {
                    'sewa' => 'Sewa',
                    'beli' => 'Beli',
                    default => '-',
                };
                $isDeviceRetrieved = (bool) $customer->device_retrieved_at;
            @endphp
            <article class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 space-y-3.5 shadow-sm hover:border-sky-300 dark:hover:border-sky-700/60 transition-all duration-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <span class="font-mono text-xs font-semibold text-sky-600 dark:text-sky-400">{{ $customer->display_id }}</span>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate mt-0.5">{{ $customer->full_name }}</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $customer->pop->name ?? '-' }}</p>
                    </div>
                    <div class="shrink-0">
                        <span class="inline-flex items-center px-2 py-0.5 text-[9px] font-bold rounded-full border {{ $isDeviceRetrieved ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800/60' : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800/60' }}">
                            {{ $isDeviceRetrieved ? 'Sudah Diambil' : 'Belum Diambil' }}
                        </span>
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs border-t border-slate-100 dark:border-slate-800/80 pt-3">
                    <div class="min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Kontrak</dt>
                        <dd class="text-slate-700 dark:text-slate-300 font-semibold mt-0.5">{{ $contractType }}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Tgl Pemutusan</dt>
                        <dd class="font-mono text-slate-700 dark:text-slate-300 font-semibold mt-0.5">{{ $customer->terminated_at ? \App\Support\IndonesianDate::date($customer->terminated_at) : '-' }}</dd>
                    </div>
                    <div class="col-span-2 min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Alasan Putus</dt>
                        <dd class="text-slate-600 dark:text-slate-400 mt-0.5 text-xs line-clamp-2" title="{{ $customer->termination_reason }}">
                            {{ $customer->termination_reason ?? '-' }}
                        </dd>
                    </div>
                </dl>

                <div class="border-t border-slate-100 dark:border-slate-800/80 pt-3 flex flex-wrap gap-2 justify-end">
                    <a href="{{ route('customers.show', $customer->id) }}"
                       class="flex-1 sm:flex-none h-10 px-3.5 text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800 rounded-lg hover:bg-sky-50 dark:hover:bg-sky-950/30 transition-colors flex items-center justify-center cursor-pointer">
                        Detail
                    </a>
                    @if(!$isDeviceRetrieved && auth()->user()->hasPermission('customers.detail.devices.retrieve'))
                    <form action="{{ route('customers.retrieve-device', $customer->id) }}" method="POST"
                          class="flex-1 sm:flex-none flex"
                          onsubmit="event.preventDefault(); window.confirmAction('Buat Task FOP pengambilan alat untuk {{ $customer->full_name }}?', this);">
                        @csrf
                        <button type="submit" class="w-full h-10 px-3.5 text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                            Ambil Alat
                        </button>
                    </form>
                    @endif
                    @if(auth()->user()->hasPermission('customers.detail.installation.validate'))
                    <form action="{{ route('customers.reactivate', $customer->id) }}" method="POST"
                          class="flex-1 sm:flex-none flex"
                          onsubmit="event.preventDefault(); window.confirmAction('Aktifkan kembali langganan {{ $customer->full_name }}?', this);">
                        @csrf
                        <button type="submit" class="w-full h-10 px-3.5 text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-950/30 transition-colors cursor-pointer">
                            Langganan Lagi
                        </button>
                    </form>
                    @endif
                </div>
            </article>
            @empty
            <div class="col-span-full py-8 text-center text-slate-400">
                Tidak ada data pelanggan putus.
            </div>
            @endforelse
        </div>
    </div>

    @include('customers.partials._list_pagination')
</div>
@endsection

@section('scripts')
@include('customers.partials._list_density_script')
@endsection
