@extends('layouts.app')

{{-- List Pelanggan Gagal — halaman sendiri (route customers.failed, permission
     customers.failed.view, CustomerFailedController). Dulu numpang
     customers/index.blade.php lewat cabang @if($statusGroup === 'failed') di
     tengah file 2000+ baris; mengubah kolom di sini berarti mengedit file yang
     sama dengan List Pelanggan biasa dan menanggung risiko regresi di sana. --}}
@php
    $pageTitle = 'Pelanggan Gagal';
@endphp

@section('title', $pageTitle . ' - Whusnet Operasional')
@section('page_title', $pageTitle)
@section('breadcrumb_parent', 'Pelanggan')
@section('breadcrumb_parent_url', '/customers')

@section('content')
@include('customers.partials._list_styles')
@include('customers.partials._list_header')
@include('customers.partials._list_stats')
@include('customers.partials._list_filters')<div class="@container bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden mb-6">
    <div class="border-b border-slate-200 dark:border-slate-800 px-6 py-3.5 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/30">
        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">Daftar Pelanggan Gagal</span>
        <a href="{{ route('customers.index') }}" class="text-xs text-sky-600 dark:text-sky-400 hover:underline">Lihat Semua Pelanggan</a>
    </div>

    {{-- DESKTOP: Table layout, visible on container screens >= 64rem --}}
    <div class="hidden @min-[64rem]:block overflow-x-auto">
        <table class="w-full min-w-[960px] border-collapse text-left text-xs" id="customerTable">
            <thead>
                <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                    <th scope="col" class="py-3.5 px-4 w-12 text-center">No</th>
                    <th scope="col" class="py-3.5 px-4">CID</th>
                    <th scope="col" class="py-3.5 px-4">Nama Pelanggan</th>
                    <th scope="col" class="py-3.5 px-4">POP</th>
                    <th scope="col" class="py-3.5 px-4">Alasan</th>
                    <th scope="col" class="py-3.5 px-4">Tgl Pemutusan</th>
                    <th scope="col" class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-slate-700 dark:text-slate-200">
                @forelse($customers as $customer)
                <tr class="hover:bg-sky-50/40 dark:hover:bg-sky-950/20 transition-colors">
                    <td class="px-4 py-3.5 text-center text-slate-400 font-mono">
                        {{ ($customers->currentPage() - 1) * $customers->perPage() + $loop->iteration }}
                    </td>
                    <td class="px-4 py-3.5 font-mono font-semibold text-sky-600 dark:text-sky-400 whitespace-nowrap">
                        {{ $customer->display_id }}
                    </td>
                    <td class="px-4 py-3.5 font-bold text-slate-900 dark:text-white">
                        {{ $customer->full_name }}
                    </td>
                    <td class="px-4 py-3.5 font-medium text-slate-700 dark:text-slate-300">
                        {{ $customer->pop->name ?? '-' }}
                    </td>
                    <td class="px-4 py-3.5 max-w-xs text-slate-600 dark:text-slate-400 truncate">
                        {{ $customer->reject_reason ?? '-' }}
                    </td>
                    <td class="px-4 py-3.5 font-mono text-slate-500 whitespace-nowrap">
                        {{ $customer->rejected_at ? \App\Support\IndonesianDate::date($customer->rejected_at) : '-' }}
                    </td>
                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                        <div class="inline-flex items-center gap-2">
                            <a href="{{ route('customers.show', $customer->id) }}"
                               class="px-2.5 py-1 text-xs font-medium text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800 rounded-lg hover:bg-sky-50 dark:hover:bg-sky-950/30 transition-colors">
                                Detail
                            </a>
                            @if(auth()->user()->hasPermission('customers.detail.installation.validate') && $customer->status_before_reject)
                            <form action="{{ route('customers.restore-from-failed', $customer->id) }}" method="POST"
                                  onsubmit="event.preventDefault(); window.confirmAction('Kembalikan {{ $customer->full_name }} ke proses sebelum ditolak?', this);">
                                @csrf
                                <button type="submit" class="px-2.5 py-1 text-xs font-medium text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-950/30 transition-colors cursor-pointer">
                                    Kembalikan
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-slate-400">Tidak ada data pelanggan gagal.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- MOBILE & TABLET: Card-based grid layout, visible on container screens < 64rem --}}
    <div class="@min-[64rem]:hidden p-4 bg-slate-50/40 dark:bg-slate-950/15 border-t border-slate-100 dark:border-slate-800/80">
        <div class="grid grid-cols-1 @min-[36rem]:grid-cols-2 gap-4">
            @forelse($customers as $customer)
            <article class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 space-y-3.5 shadow-sm hover:border-sky-300 dark:hover:border-sky-700/60 transition-all duration-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <span class="font-mono text-xs font-semibold text-sky-600 dark:text-sky-400">{{ $customer->display_id }}</span>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate mt-0.5">{{ $customer->full_name }}</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $customer->pop->name ?? '-' }}</p>
                    </div>
                    <div class="shrink-0">
                        <span class="inline-flex items-center px-2 py-0.5 text-[9px] font-bold rounded-full border bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800/60">
                            Gagal
                        </span>
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs border-t border-slate-100 dark:border-slate-800/80 pt-3">
                    <div class="min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">No</dt>
                        <dd class="text-slate-700 dark:text-slate-300 font-semibold mt-0.5">
                            {{ ($customers->currentPage() - 1) * $customers->perPage() + $loop->iteration }}
                        </dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Tgl Pemutusan</dt>
                        <dd class="font-mono text-slate-700 dark:text-slate-300 font-semibold mt-0.5 font-mono">
                            {{ $customer->rejected_at ? \App\Support\IndonesianDate::date($customer->rejected_at) : '-' }}
                        </dd>
                    </div>
                    <div class="col-span-2 min-w-0">
                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Alasan</dt>
                        <dd class="text-slate-600 dark:text-slate-400 mt-0.5 text-xs line-clamp-2" title="{{ $customer->reject_reason }}">
                            {{ $customer->reject_reason ?? '-' }}
                        </dd>
                    </div>
                </dl>

                <div class="border-t border-slate-100 dark:border-slate-800/80 pt-3 flex flex-wrap gap-2 justify-end">
                    <a href="{{ route('customers.show', $customer->id) }}"
                       class="flex-1 sm:flex-none h-10 px-3.5 text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-800 rounded-lg hover:bg-sky-50 dark:hover:bg-sky-950/30 transition-colors flex items-center justify-center cursor-pointer">
                        Detail
                    </a>
                    @if(auth()->user()->hasPermission('customers.detail.installation.validate') && $customer->status_before_reject)
                    <form action="{{ route('customers.restore-from-failed', $customer->id) }}" method="POST"
                          class="flex-1 sm:flex-none flex"
                          onsubmit="event.preventDefault(); window.confirmAction('Kembalikan {{ $customer->full_name }} ke proses sebelum ditolak?', this);">
                        @csrf
                        <button type="submit" class="w-full h-10 px-3.5 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800 rounded-lg hover:bg-amber-50 dark:hover:bg-amber-950/30 transition-colors cursor-pointer">
                            Kembalikan
                        </button>
                    </form>
                    @endif
                </div>
            </article>
            @empty
            <div class="col-span-full py-8 text-center text-slate-400">
                Tidak ada data pelanggan gagal.
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
