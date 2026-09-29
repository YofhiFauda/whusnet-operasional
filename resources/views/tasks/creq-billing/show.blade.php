@extends('layouts.app')

@section('title', 'Verifikasi Biaya C-REQ - ' . $task->task_number)
@section('page_title', 'Verifikasi Biaya C-REQ')
@section('breadcrumb_parent', 'Verifikasi Biaya C-REQ')
@section('breadcrumb_parent_url', route('tasks.creq-billing.index'))

@php
    $detail = $task->creqDetail;
    $customer = $task->customer;
    $service = $customer?->customerService;
    $maintenance = $task->maintenanceReport;
    $manualCategory = $detail->category?->toManualInvoiceCategory();
    $customerHasService = (bool) $service;

    // Formatting Status Badges
    $status = $detail->verification_status->value;
    $statusBadgeClass = match($status) {
        'verified' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/60',
        'rejected' => 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800/60',
        default => 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800/60',
    };
    $statusDotClass = match($status) {
        'verified' => 'bg-emerald-500',
        'rejected' => 'bg-rose-500',
        default => 'bg-amber-500 animate-pulse',
    };

    // Customer WhatsApp URL
    $phone = $customer?->primary_phone ?? $customer?->phone ?? '';
    $phoneFormatted = $phone ? preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $phone)) : '';
    $waUrl = $phoneFormatted ? "https://wa.me/{$phoneFormatted}" : null;

    // Photo URLs (validated with foto_publik)
    $opmPhotoUrl = $maintenance ? foto_publik($maintenance->opm_photo) : null;
    $speedtestPhotoUrl = $maintenance ? foto_publik($maintenance->speedtest_photo) : null;

    // Material/SN/alat kerja menempel di FopTask (bukan task_maintenances) —
    // resolusi anchor sama persis Detail Task (tasks/show.blade.php), supaya
    // CS menilai biaya dari laporan yang LENGKAP, bukan cuma catatan biaya.
    $reportFopTask = app(\App\Services\TaskWorkToolService::class)->resolveTaskFor($task);
    $reportMaterials = $reportFopTask ? $reportFopTask->materials()->terpakai()->orderBy('id')->get() : collect();
    $reportInstalledSerials = $reportFopTask
        ? \App\Models\InventoryTransaction::where('fop_task_id', $reportFopTask->id)
            ->where('type', \App\Enums\InventoryTransactionType::INSTALL->value)
            ->with(['serial.item', 'item'])
            ->get()
        : collect();
    $reportWorkTools = $reportFopTask ? $reportFopTask->workTools()->orderBy('id')->get() : collect();

    $approveHasErrors = $errors->hasAny(['amount', 'description', 'manual_subtype_name', 'customer_id']);
    $rejectHasErrors = $errors->has('reason');
@endphp

@section('content')
<div class="max-w-[1240px] mx-auto space-y-5 pb-16 md:pb-10" x-data="creqBillingView()">

    {{-- ══ LAYER 1: NAKED PAGE HEADER ══════════════════════════════════════════ --}}
    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
        <div class="space-y-1.5 min-w-0">
            {{-- Breadcrumb Strip --}}
            <nav class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                <a href="{{ route('tasks.creq-billing.index') }}" class="hover:text-sky-600 dark:hover:text-sky-400 transition-colors font-medium">Verifikasi Biaya C-REQ</a>
                <svg class="h-3 w-3 shrink-0 text-slate-400 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
                <span class="font-mono text-slate-700 dark:text-slate-200 font-semibold">{{ $task->task_number }}</span>
            </nav>

            {{-- Title Row --}}
            <div class="flex items-center gap-2.5 flex-wrap">
                <a href="{{ route('tasks.creq-billing.index') }}"
                   class="h-9 w-9 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-600 dark:text-slate-300 transition-all active:scale-95 shadow-2xs shrink-0 cursor-pointer"
                   title="Kembali ke Daftar Antrean">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>

                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-50 tracking-tight leading-tight">
                    {{ $customer?->full_name ?? 'Pelanggan Tidak Diketahui' }}
                </h1>

                {{-- Task Number Technical Chip with Copy --}}
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-slate-700 dark:text-slate-200 shadow-2xs">
                    <span>{{ $task->task_number }}</span>
                    <button type="button" @click="copyText('{{ $task->task_number }}', 'No. Task')" class="text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 transition-colors cursor-pointer" title="Salin No. Task">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </button>
                </div>

                {{-- Verification Status Badge --}}
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border shadow-2xs {{ $statusBadgeClass }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $statusDotClass }}"></span>
                    {{ $detail->verification_status->label() }}
                </span>

                {{-- Category Tag --}}
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 border border-sky-200 dark:border-sky-800/60">
                    {{ $detail->category?->label() }}
                </span>
            </div>

            {{-- Metadata Subtitle --}}
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 flex-wrap">
                <span class="flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    POP {{ $task->pop?->name ?? 'Standard' }}
                </span>
                <span>&bull;</span>
                <span>Selesai: <strong class="font-mono text-slate-700 dark:text-slate-300">{{ $task->completed_at ? \App\Support\IndonesianDate::dateTime($task->completed_at) : '—' }}</strong></span>
                @if($detail->verified_at)
                <span>&bull;</span>
                <span>Diverifikasi: <strong class="font-mono text-slate-700 dark:text-slate-300">{{ \App\Support\IndonesianDate::dateTime($detail->verified_at) }}</strong> oleh <strong>{{ $detail->verifier?->name ?? 'CS' }}</strong></span>
                @endif
            </div>
        </div>

        {{-- Top Action Buttons --}}
        <div class="flex items-center gap-2.5 flex-wrap shrink-0">
            <a href="{{ route('tasks.show', $task) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-all shadow-2xs active:scale-95">
                <span>Detail Task Lengkap</span>
                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                </svg>
            </a>
        </div>
    </div>

    {{-- ══ LAYER 2: ALERT BANNERS (CONDITIONAL) ═════════════════════════════════ --}}
    @if(! $customerHasService)
    <div class="rounded-lg border border-amber-200 dark:border-amber-900/60 bg-amber-50 dark:bg-amber-950/30 p-4 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-3 shadow-2xs">
        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <div class="space-y-1 flex-1">
            <p class="font-bold">Pelanggan belum memiliki layanan internet aktif</p>
            <p class="text-amber-700 dark:text-amber-400/90 leading-relaxed">
                Tagihan Manual hanya dapat diterbitkan untuk pelanggan yang memiliki data layanan aktif. Pastikan aktivasi layanan pelanggan sudah selesai di sistem sebelum menyetujui biaya C-REQ ini.
            </p>
            @if($customer)
            <div class="pt-1">
                <a href="{{ route('customers.show', $customer) }}" class="font-bold text-amber-900 dark:text-amber-200 underline hover:no-underline inline-flex items-center gap-1">
                    Buka Profil Pelanggan &rarr;
                </a>
            </div>
            @endif
        </div>
    </div>
    @endif

    @error('customer_id')
    <div class="rounded-lg border border-rose-200 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/30 p-4 text-xs text-rose-800 dark:text-rose-300 flex items-center gap-3">
        <svg class="h-5 w-5 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>{{ $message }}</span>
    </div>
    @enderror

    {{-- ══ LAYER 3: PRIMARY DETAIL PANEL (SINGLE CARD BUDGET = 1) ═══════════════ --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-xs">

        {{-- ── METRIC STRIP (FLAT BAR WITH VERTICAL DIVIDERS) ─────────────────── --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-slate-200 dark:divide-slate-700 border-b border-slate-200 dark:border-slate-700 bg-slate-50/70 dark:bg-slate-900/40">
            
            {{-- Col 1: Pelanggan --}}
            <div class="p-3.5 sm:p-4 flex flex-col justify-between min-w-0">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">PELANGGAN</span>
                <div class="mt-1">
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate block" title="{{ $customer?->full_name ?? '-' }}">
                        {{ $customer?->full_name ?? '-' }}
                    </span>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $customer?->cid ?? $customer?->customer_code ?? 'CID: -' }}</span>
                        @if($customer?->cid)
                        <button type="button" @click="copyText('{{ $customer->cid }}', 'CID')" class="text-slate-400 hover:text-sky-600 transition-colors" title="Salin CID">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </button>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Col 2: POP / Cabang --}}
            <div class="p-3.5 sm:p-4 flex flex-col justify-between min-w-0">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">POP / CABANG</span>
                <div class="mt-1">
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate block">{{ $task->pop?->name ?? 'Standard' }}</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 block mt-0.5 truncate">{{ $task->pop?->city ?? 'Area Operasional' }}</span>
                </div>
            </div>

            {{-- Col 3: Kategori Pekerjaan --}}
            <div class="p-3.5 sm:p-4 flex flex-col justify-between min-w-0">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">KATEGORI C-REQ</span>
                <div class="mt-1">
                    <span class="text-xs font-bold text-sky-700 dark:text-sky-400 truncate block">{{ $detail->category?->label() }}</span>
                    <span class="text-[11px] text-slate-500 dark:text-slate-400 block mt-0.5 truncate">
                        {{ $detail->category_custom_name ?: 'Standar C-REQ' }}
                    </span>
                </div>
            </div>

            {{-- Col 4: Status Verifikasi CS --}}
            <div class="p-3.5 sm:p-4 flex flex-col justify-between min-w-0">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">STATUS VERIFIKASI</span>
                <div class="mt-1 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full {{ $statusDotClass }}"></span>
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ $detail->verification_status->label() }}</span>
                </div>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block mt-0.5">
                    {{ $detail->verified_by ? ($detail->verifier?->name ?? 'Oleh CS') : 'Menunggu review' }}
                </span>
            </div>

            {{-- Col 5: Tagihan Manual --}}
            <div class="p-3.5 sm:p-4 flex flex-col justify-between col-span-2 sm:col-span-1 min-w-0 bg-slate-100/50 dark:bg-slate-900/60">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">TAGIHAN MANUAL</span>
                <div class="mt-1">
                    @if($detail->invoice)
                        <span class="text-xs font-bold font-mono text-emerald-600 dark:text-emerald-400 block leading-tight">
                            Rp {{ number_format((float) $detail->invoice->total_amount, 0, ',', '.') }}
                        </span>
                        <span class="text-[10px] font-mono text-slate-500 dark:text-slate-400 block mt-0.5 truncate">
                            {{ $detail->invoice->invoice_number }} &bull; {{ $detail->invoice->invoice_status?->label() }}
                        </span>
                    @elseif($status === 'rejected')
                        <span class="text-xs font-bold text-rose-600 dark:text-rose-400 block">Ditolak</span>
                        <span class="text-[10px] text-slate-500 dark:text-slate-400 block mt-0.5">Tanpa tagihan</span>
                    @else
                        <span class="text-xs font-bold text-amber-600 dark:text-amber-400 block">Belum Diterbitkan</span>
                        <span class="text-[10px] text-slate-500 dark:text-slate-400 block mt-0.5">Input nominal CS</span>
                    @endif
                </div>
            </div>

        </div>

        {{-- ── 2-COLUMN MAIN CONTENT (LEFT: 7/12, RIGHT: 5/12) ────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-slate-200 dark:divide-slate-700">

            {{-- ═════════════════════════════════════════════════════════════════
                 LEFT COLUMN: RINCIAN LAPORAN C-REQ TEKNIS (7/12)
            ═════════════════════════════════════════════════════════════════ --}}
            <div class="lg:col-span-7 p-4 sm:p-6 space-y-6">

                {{-- Section Title --}}
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Rincian Laporan C-REQ Teknis
                        </h2>
                    </div>
                    <span class="text-[11px] font-mono text-slate-400 dark:text-slate-500 font-medium">Task #{{ $task->id }}</span>
                </div>

                {{-- Info Rows Grid --}}
                <div class="space-y-3.5 text-xs">

                    {{-- Judul Task & Deskripsi --}}
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">JUDUL &amp; DESKRIPSI PEKERJAAN</span>
                        <div class="bg-slate-50 dark:bg-slate-900/40 rounded-lg p-3 border border-slate-200/80 dark:border-slate-700/80">
                            <h3 class="font-bold text-slate-900 dark:text-slate-100 text-sm leading-snug">{{ $task->title }}</h3>
                            @if($task->description)
                            <p class="text-slate-600 dark:text-slate-300 mt-1.5 leading-relaxed whitespace-pre-line text-xs">{{ $task->description }}</p>
                            @endif
                        </div>
                    </div>

                    {{-- Kategori & Spesifikasi Perubahan --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div class="bg-slate-50 dark:bg-slate-900/40 rounded-lg p-3 border border-slate-200/80 dark:border-slate-700/80">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block select-none">Kategori Pekerjaan</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 text-xs mt-1 block">{{ $detail->category?->label() }}</span>
                        </div>
                        @if($detail->category_custom_name)
                        <div class="bg-slate-50 dark:bg-slate-900/40 rounded-lg p-3 border border-slate-200/80 dark:border-slate-700/80">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block select-none">Nama Kategori Kustom</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 text-xs mt-1 block">{{ $detail->category_custom_name }}</span>
                        </div>
                        @endif
                    </div>

                    {{-- Titik Koordinat (Tikor Lama vs Baru jika ada) --}}
                    @if($detail->tikor_lama_lat || $detail->tikor_baru_lat)
                    <div class="space-y-1.5 pt-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">TITIK KOORDINAT (GPS)</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            {{-- Tikor Lama --}}
                            <div class="bg-slate-50 dark:bg-slate-900/40 rounded-lg p-3 border border-slate-200/80 dark:border-slate-700/80 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase">Tikor Lama</span>
                                    @if($detail->tikor_lama_lat && $detail->tikor_lama_lng)
                                    <a href="https://www.google.com/maps/search/?api=1&query={{ $detail->tikor_lama_lat }},{{ $detail->tikor_lama_lng }}" target="_blank"
                                       class="text-[10px] text-sky-600 dark:text-sky-400 hover:underline inline-flex items-center gap-0.5 font-semibold">
                                        <span>Maps</span>
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                    @endif
                                </div>
                                <p class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $detail->tikor_lama_lat ?? '-' }}, {{ $detail->tikor_lama_lng ?? '-' }}
                                </p>
                            </div>

                            {{-- Tikor Baru --}}
                            <div class="bg-slate-50 dark:bg-slate-900/40 rounded-lg p-3 border border-slate-200/80 dark:border-slate-700/80 space-y-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase">Tikor Baru</span>
                                    @if($detail->tikor_baru_lat && $detail->tikor_baru_lng)
                                    <a href="https://www.google.com/maps/search/?api=1&query={{ $detail->tikor_baru_lat }},{{ $detail->tikor_baru_lng }}" target="_blank"
                                       class="text-[10px] text-sky-600 dark:text-sky-400 hover:underline inline-flex items-center gap-0.5 font-semibold">
                                        <span>Maps</span>
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                    @endif
                                </div>
                                <p class="font-mono text-xs font-semibold text-slate-800 dark:text-slate-200">
                                    {{ $detail->tikor_baru_lat ?? '-' }}, {{ $detail->tikor_baru_lng ?? '-' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Catatan Biaya dari Teknisi (Highlight Callout) --}}
                    <div class="space-y-1 pt-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">CATATAN BIAYA DARI TEKNISI</span>
                        <div class="bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 rounded-lg p-3.5 space-y-1">
                            <div class="flex items-center gap-1.5 text-amber-700 dark:text-amber-400 font-bold text-[11px]">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                </svg>
                                <span>Keterangan Biaya yang Diajukan Teknisi:</span>
                            </div>
                            <p class="text-xs text-slate-800 dark:text-slate-200 whitespace-pre-line leading-relaxed pl-5 font-medium">
                                {{ $detail->billing_note ?: 'Tidak ada catatan biaya khusus yang ditulis oleh teknisi.' }}
                            </p>
                        </div>
                    </div>

                    {{-- Kendala Teknis (jika ada laporan MTN) --}}
                    @if($maintenance && $maintenance->kendala_teknis)
                    <div class="space-y-1 pt-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">KENDALA TEKNIS LAPANGAN</span>
                        <div class="bg-slate-50 dark:bg-slate-900/40 rounded-lg p-3 border border-slate-200/80 dark:border-slate-700/80">
                            <p class="text-slate-700 dark:text-slate-300 text-xs whitespace-pre-line leading-relaxed">{{ $maintenance->kendala_teknis }}</p>
                        </div>
                    </div>
                    @endif

                    {{-- Modem/Perangkat Aktif Terpasang --}}
                    @if($reportInstalledSerials->isNotEmpty())
                    <div class="space-y-1.5 pt-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">MODEM / PERANGKAT AKTIF TERPASANG</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            @foreach($reportInstalledSerials as $tx)
                            <div class="bg-slate-50 dark:bg-slate-900/40 rounded-lg p-3 border border-slate-200/80 dark:border-slate-700/80">
                                <span class="font-semibold text-slate-800 dark:text-slate-200 block">{{ $tx->item->name ?? '-' }}</span>
                                <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">SN {{ $tx->serial?->serial_number ?? '—' }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Material Terpakai (termasuk potongan roll kabel) --}}
                    @if($reportMaterials->isNotEmpty())
                    <div class="space-y-1.5 pt-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">MATERIAL TERPAKAI</span>
                        <div class="bg-slate-50 dark:bg-slate-900/40 rounded-lg border border-slate-200/80 dark:border-slate-700/80 divide-y divide-slate-200/80 dark:divide-slate-700/80">
                            @foreach($reportMaterials as $material)
                            <div class="flex justify-between gap-3 px-3 py-2">
                                <span class="text-slate-700 dark:text-slate-300 min-w-0">{{ $material->item_name }}@if($material->lot_no)<span class="font-mono text-[10px] text-slate-400"> · Roll {{ $material->lot_no }}</span>@endif @if($material->note)<span class="text-[10px] text-slate-400"> · {{ $material->note }}</span>@endif</span>
                                <span class="font-mono font-semibold text-slate-800 dark:text-slate-200 shrink-0">{{ rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',') }} {{ $material->unit }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Alat Kerja Dipakai --}}
                    @if($reportWorkTools->isNotEmpty())
                    <div class="space-y-1.5 pt-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 select-none">ALAT KERJA DIPAKAI</span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($reportWorkTools as $tool)
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">{{ $tool->tool_name }}@if($tool->note)<span class="text-slate-400 text-[10px]"> · {{ $tool->note }}</span>@endif</span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Bukti Foto Dokumentasi --}}
                    @if($opmPhotoUrl || $speedtestPhotoUrl)
                    <div class="space-y-2 pt-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block select-none">BUKTI FOTO DOKUMENTASI</span>
                        <div class="grid grid-cols-2 gap-3">
                            @if($opmPhotoUrl)
                            <div class="bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 rounded-lg p-2.5 text-center space-y-2">
                                <span class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 block">Foto OPM / Redaman</span>
                                <div class="relative group cursor-pointer overflow-hidden rounded-md border border-slate-200 dark:border-slate-700"
                                     @click="$dispatch('open-image-preview', { url: '{{ $opmPhotoUrl }}', label: 'Foto OPM / Redaman - {{ $task->task_number }}' })">
                                    <img src="{{ $opmPhotoUrl }}" alt="Foto OPM" class="h-28 w-full object-cover group-hover:scale-105 transition-transform duration-200">
                                    <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-[11px] font-semibold">
                                        Klik untuk Perbesar
                                    </div>
                                </div>
                            </div>
                            @endif

                            @if($speedtestPhotoUrl)
                            <div class="bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 rounded-lg p-2.5 text-center space-y-2">
                                <span class="text-[10px] font-bold uppercase text-slate-500 dark:text-slate-400 block">Foto Speedtest</span>
                                <div class="relative group cursor-pointer overflow-hidden rounded-md border border-slate-200 dark:border-slate-700"
                                     @click="$dispatch('open-image-preview', { url: '{{ $speedtestPhotoUrl }}', label: 'Foto Speedtest - {{ $task->task_number }}' })">
                                    <img src="{{ $speedtestPhotoUrl }}" alt="Foto Speedtest" class="h-28 w-full object-cover group-hover:scale-105 transition-transform duration-200">
                                    <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white text-[11px] font-semibold">
                                        Klik untuk Perbesar
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- Informasi Teknisi & Tim --}}
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-700/60 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-slate-600 dark:text-slate-300">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span>FOP Koordinator: <strong class="text-slate-800 dark:text-slate-200">{{ $task->fop?->name ?? '—' }}</strong></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Diselesaikan oleh: <strong class="text-slate-800 dark:text-slate-200">{{ $task->completedBy?->name ?? 'Teknisi' }}</strong></span>
                        </div>
                    </div>

                </div>

            </div>

            {{-- ═════════════════════════════════════════════════════════════════
                 RIGHT COLUMN: STATUS & AKSI VERIFIKASI BIAYA (5/12)
            ═════════════════════════════════════════════════════════════════ --}}
            <div class="lg:col-span-5 p-4 sm:p-6 bg-slate-50/50 dark:bg-slate-900/30 space-y-6">

                {{-- Section Title --}}
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            Status &amp; Verifikasi Finansial
                        </h2>
                    </div>
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full border {{ $statusBadgeClass }}">
                        {{ $detail->verification_status->label() }}
                    </span>
                </div>

                {{-- ── CASE A: STATUS DIVERIFIKASI (VERIFIED) ─────────────────── --}}
                @if($status === 'verified')
                <div class="space-y-4">
                    <div class="rounded-lg border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50/70 dark:bg-emerald-950/30 p-4 space-y-3">
                        <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-bold text-xs">
                            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Biaya C-REQ Telah Disetujui &amp; Ditagihkan</span>
                        </div>
                        <p class="text-xs text-emerald-700 dark:text-emerald-400/90 leading-relaxed">
                            Verifikasi telah diselesaikan oleh <strong class="text-emerald-900 dark:text-emerald-200">{{ $detail->verifier?->name ?? 'CS' }}</strong> pada <strong class="font-mono text-emerald-900 dark:text-emerald-200">{{ \App\Support\IndonesianDate::dateTime($detail->verified_at) }}</strong>.
                        </p>
                    </div>

                    @if($detail->invoice)
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-4 space-y-3 shadow-2xs">
                        <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-700/60 pb-2.5">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">TAGIHAN MANUAL TERBIT</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-600">
                                {{ $detail->invoice->invoice_status?->label() }}
                            </span>
                        </div>

                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">Nominal Tagihan</span>
                            <span class="text-xl sm:text-2xl font-extrabold font-mono text-slate-900 dark:text-slate-100 block mt-0.5">
                                Rp {{ number_format((float) $detail->invoice->total_amount, 0, ',', '.') }}
                            </span>
                        </div>

                        <div class="space-y-1.5 text-xs text-slate-600 dark:text-slate-300 pt-1">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">No. Invoice:</span>
                                <div class="inline-flex items-center gap-1.5 font-mono font-bold text-slate-800 dark:text-slate-200">
                                    <span>{{ $detail->invoice->invoice_number }}</span>
                                    <button type="button" @click="copyText('{{ $detail->invoice->invoice_number }}', 'No. Invoice')" class="text-slate-400 hover:text-sky-600 transition-colors cursor-pointer" title="Salin No. Invoice">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Kategori:</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $detail->invoice->manual_category?->label() ?? $detail->category?->label() }}</span>
                            </div>
                            @if($detail->invoice->manual_subtype_name)
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Jenis:</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $detail->invoice->manual_subtype_name }}</span>
                            </div>
                            @endif
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Deskripsi:</span>
                                <span class="font-medium text-slate-700 dark:text-slate-300 text-right max-w-[200px] truncate" title="{{ $detail->invoice->description }}">{{ $detail->invoice->description }}</span>
                            </div>
                        </div>

                        <div class="pt-2">
                            <a href="{{ route('invoices.show', $detail->invoice) }}"
                               class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors shadow-2xs active:scale-[0.98]">
                                <span>Lihat &amp; Cetak Faktur Tagihan</span>
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- ── CASE B: STATUS DITOLAK (REJECTED) ──────────────────────── --}}
                @elseif($status === 'rejected')
                <div class="space-y-4">
                    <div class="rounded-lg border border-rose-200 dark:border-rose-900/60 bg-rose-50/70 dark:bg-rose-950/30 p-4 space-y-3">
                        <div class="flex items-center gap-2 text-rose-800 dark:text-rose-300 font-bold text-xs">
                            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span>Biaya C-REQ Ditolak oleh CS</span>
                        </div>
                        <p class="text-xs text-rose-700 dark:text-rose-400/90 leading-relaxed">
                            Ditolak oleh <strong class="text-rose-900 dark:text-rose-200">{{ $detail->verifier?->name ?? 'CS' }}</strong> pada <strong class="font-mono text-rose-900 dark:text-rose-200">{{ \App\Support\IndonesianDate::dateTime($detail->verified_at) }}</strong>. Tagihan manual tidak diterbitkan.
                        </p>
                    </div>

                    @if($detail->rejection_reason)
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-4 space-y-2 shadow-2xs">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400 block select-none">
                            ALASAN PENOLAKAN
                        </span>
                        <p class="text-xs text-slate-800 dark:text-slate-200 leading-relaxed whitespace-pre-line bg-rose-50/40 dark:bg-rose-950/20 p-3 rounded-lg border border-rose-100 dark:border-rose-900/40 font-medium">
                            {{ $detail->rejection_reason }}
                        </p>
                    </div>
                    @endif
                </div>

                {{-- ── CASE C: STATUS MENUNGGU VERIFIKASI (PENDING) ───────────── --}}
                @else
                <div class="space-y-4" x-data="{ activeAction: '{{ $rejectHasErrors ? 'reject' : 'approve' }}' }">
                    
                    {{-- Action Mode Selector Tabs (Pills) --}}
                    @if($customerHasService)
                    <div class="flex items-center gap-1.5 p-1 bg-slate-200/70 dark:bg-slate-700/60 rounded-lg select-none">
                        @can('creq_billing_verification.approve')
                        <button type="button" @click="activeAction = 'approve'"
                                :class="activeAction === 'approve'
                                    ? 'bg-white dark:bg-slate-800 text-emerald-700 dark:text-emerald-400 font-bold shadow-2xs'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 font-medium'"
                                class="flex-1 py-2 px-3 rounded-md text-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <span>Setujui &amp; Terbitkan</span>
                        </button>
                        @endcan

                        @can('creq_billing_verification.reject')
                        <button type="button" @click="activeAction = 'reject'"
                                :class="activeAction === 'reject'
                                    ? 'bg-white dark:bg-slate-800 text-rose-700 dark:text-rose-400 font-bold shadow-2xs'
                                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 font-medium'"
                                class="flex-1 py-2 px-3 rounded-md text-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            <span>Tolak Biaya</span>
                        </button>
                        @endcan
                    </div>
                    @endif

                    {{-- ── FORM APPROVE & ISSUE INVOICE ──────────────────────── --}}
                    @can('creq_billing_verification.approve')
                    @if($customerHasService)
                    <div x-show="activeAction === 'approve'" x-cloak class="space-y-4">
                        <form action="{{ route('tasks.creq-billing.approve', $task) }}" method="POST" class="space-y-4" @submit="isSubmitting = true">
                            @csrf
                            @method('PUT')

                            <div class="bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/80 dark:border-emerald-900/60 rounded-lg p-3.5 text-xs text-emerald-800 dark:text-emerald-300 space-y-1">
                                <div class="font-bold flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>Penerbitan Tagihan Manual Otomatis</span>
                                </div>
                                <p class="text-[11px] text-emerald-700 dark:text-emerald-400 leading-relaxed">
                                    Kategori tagihan otomatis dipetakan ke <strong>{{ $manualCategory->label() }}</strong>. Masukkan nominal tagihan sesuai tarif resmi yang berlaku.
                                </p>
                            </div>

                            {{-- Subtype Name (if required) --}}
                            @if($manualCategory->requiresSubtypeName())
                            <div class="space-y-1">
                                <label for="manual_subtype_name" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Nama Jenis Tagihan <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="manual_subtype_name" id="manual_subtype_name" maxlength="150" required
                                       value="{{ old('manual_subtype_name', $detail->category_custom_name) }}"
                                       placeholder="Contoh: Pasang Kabel Ekstra / Repeater"
                                       class="w-full text-xs px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-colors shadow-2xs">
                                @error('manual_subtype_name')
                                <p class="text-[11px] text-rose-600 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>
                            @endif

                            {{-- Description --}}
                            <div class="space-y-1">
                                <label for="description" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Deskripsi Tagihan <span class="text-rose-500">*</span>
                                </label>
                                <textarea name="description" id="description" rows="3" maxlength="1000" required
                                          placeholder="Uraikan detail pekerjaan yang ditagihkan kepada pelanggan..."
                                          class="w-full text-xs px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-colors shadow-2xs leading-relaxed">{{ old('description', $detail->billing_note) }}</textarea>
                                <div class="flex items-center justify-between text-[10px] text-slate-400 dark:text-slate-500">
                                    <span>Pra-terisi dari catatan teknisi &bull; dapat disunting</span>
                                    <span>Maks. 1000 karakter</span>
                                </div>
                                @error('description')
                                <p class="text-[11px] text-rose-600 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Nominal Tagihan (Currency Input) --}}
                            <div class="space-y-1">
                                <label for="amount" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Nominal Tagihan (Rupiah) <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 font-mono text-xs font-bold">
                                        Rp
                                    </div>
                                    <input type="text" inputmode="numeric" name="amount" id="amount" data-rupiah
                                           value="{{ old('amount') }}" required placeholder="0"
                                           class="w-full pl-10 pr-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-sm font-mono font-bold focus:outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition-colors shadow-2xs">
                                </div>
                                <p class="text-[10px] text-slate-400 dark:text-slate-500">Wajib diinput CS sesuai tarif resmi (tidak otomatis dari teknisi).</p>
                                @error('amount')
                                <p class="text-[11px] text-rose-600 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Action Submit Button --}}
                            <div class="pt-2">
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors shadow-2xs active:scale-[0.98] cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span>Setujui &amp; Terbitkan Tagihan</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    @endif
                    @endcan

                    {{-- ── FORM REJECT ────────────────────────────────────────── --}}
                    @can('creq_billing_verification.reject')
                    <div x-show="activeAction === 'reject'" x-cloak class="space-y-4">
                        <form action="{{ route('tasks.creq-billing.reject', $task) }}" method="POST" class="space-y-4" @submit="isSubmitting = true">
                            @csrf
                            @method('PUT')

                            <div class="bg-rose-50/50 dark:bg-rose-950/20 border border-rose-200/80 dark:border-rose-900/60 rounded-lg p-3.5 text-xs text-rose-800 dark:text-rose-300 space-y-1">
                                <div class="font-bold flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                    <span>Konfirmasi Penolakan Biaya</span>
                                </div>
                                <p class="text-[11px] text-rose-700 dark:text-rose-400 leading-relaxed">
                                    Penolakan ini akan membatalkan pengajuan biaya teknisi sehingga tidak ada invoice yang ditagihkan ke pelanggan.
                                </p>
                            </div>

                            <div class="space-y-1">
                                <label for="reason" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Alasan Penolakan <span class="text-rose-500">*</span>
                                </label>
                                <textarea name="reason" id="reason" rows="4" required maxlength="1000"
                                          placeholder="Jelaskan alasan penolakan biaya pengajuan ini secara jelas..."
                                          class="w-full text-xs px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-rose-500 focus:ring-2 focus:ring-rose-500/20 transition-colors shadow-2xs leading-relaxed">{{ old('reason') }}</textarea>
                                <div class="flex items-center justify-between text-[10px] text-slate-400 dark:text-slate-500">
                                    <span>Wajib diisi &bull; tersimpan di Audit Log</span>
                                    <span>Maks. 1000 karakter</span>
                                </div>
                                @error('reason')
                                <p class="text-[11px] text-rose-600 font-semibold">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="pt-2">
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg transition-colors shadow-2xs active:scale-[0.98] cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span>Konfirmasi Tolak Biaya</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    @endcan

                </div>
                @endif

            </div>

        </div>

    </div>

</div>

{{-- Global Modal Preview Foto --}}
<x-ui.image-preview-modal />

{{-- Alpine Component Logic --}}
<script>
function creqBillingView() {
    return {
        isSubmitting: false,
        copyText(text, label) {
            if (!text) return;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    if (window.$toast) {
                        window.$toast.success(`${label} berhasil disalin: ${text}`);
                    } else {
                        alert(`${label} disalin ke clipboard: ${text}`);
                    }
                }).catch(() => {
                    this.fallbackCopy(text, label);
                });
            } else {
                this.fallbackCopy(text, label);
            }
        },
        fallbackCopy(text, label) {
            const temp = document.createElement('textarea');
            temp.value = text;
            temp.style.position = 'fixed';
            temp.style.left = '-9999px';
            document.body.appendChild(temp);
            temp.select();
            try {
                document.execCommand('copy');
                if (window.$toast) {
                    window.$toast.success(`${label} berhasil disalin: ${text}`);
                }
            } catch (e) {
                console.error('Copy failed', e);
            }
            document.body.removeChild(temp);
        }
    };
}
</script>
@endsection
