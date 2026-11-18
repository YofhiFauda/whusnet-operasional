@extends('layouts.app')

@section('title', 'Review Penarikan Alat - '.$customer->full_name.' - Whusnet Operasional')
@section('breadcrumb_parent', 'Review Penarikan Alat')
@section('breadcrumb_parent_url', route('device-retrieval-reviews.index'))
@section('page_title', $customer->full_name)

@php
    $tolerance = \App\Services\DeviceRetrievalReviewService::TOLERANCE_ATTEMPTS;
    $attemptCount = $attempts->count();
    $withinTolerance = \App\Services\DeviceRetrievalReviewService::isWithinTolerance($attemptCount);
    $latest = $attempts->last();
    $device = $customer->customerDevice;
    // Detail selalu kembali ke dirinya sendiri; `tab` cuma dibawa untuk link kembali.
    $tab = \App\Services\DeviceRetrievalReviewService::TAB_MENUNGGU;

    // Status kasus = hasil akhir kalau sudah ada, kalau belum ikut laporan terakhir.
    [$caseLabel, $caseBadge] = match (true) {
        (bool) $device?->retrieval_waived_at => ['Dibebaskan', 'badge text-violet-700 bg-violet-50 border-violet-200 dark:text-violet-300 dark:bg-violet-950/50 dark:border-violet-800/60'],
        (bool) $device?->device_retrieved_at => ['Alat sudah diambil', 'badge badge-success'],
        $latest?->review_status === \App\Enums\DeviceRetrievalReviewStatus::MENUNGGU => ['Menunggu review', 'badge badge-warning'],
        $latest?->review_status === \App\Enums\DeviceRetrievalReviewStatus::DITOLAK => ['Deact diulang', 'badge badge-error'],
        default => [$latest?->review_status->label() ?? '-', 'badge badge-neutral'],
    };
    $isOpenCase = ! $device?->retrieval_waived_at && ! $device?->device_retrieved_at;
    $metricLabel = 'text-[10px] font-semibold uppercase tracking-[0.07em] text-slate-500 dark:text-slate-400';
@endphp

@section('content')
<div class="max-w-[1180px] mx-auto pb-16 md:pb-8">

    {{-- ══ Page Header — naked (Design.md §1.7) ══ --}}
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                <h1 class="text-[22px] font-bold leading-tight text-slate-900 dark:text-slate-50 tracking-tight">{{ $customer->full_name }}</h1>
                <span class="{{ $caseBadge }}">{{ $caseLabel }}</span>
            </div>
            <p class="mt-1 flex flex-wrap items-center gap-x-1.5 text-[13px] text-slate-500 dark:text-slate-400">
                <span class="font-mono text-sky-700 dark:text-sky-400">{{ $customer->display_id }}</span>
                <span class="text-slate-300 dark:text-slate-600">·</span>
                <span>{{ $customer->pop->name ?? '-' }}</span>
            </p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2">
            <a href="{{ route('device-retrieval-reviews.index') }}" class="btn-secondary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                Kembali
            </a>
            @if(auth()->user()->hasPermission('customers.detail.view'))
            <a href="{{ route('customers.show', $customer) }}" class="btn-secondary">Detail Pelanggan</a>
            @endif
        </div>
    </div>

    @if($errors->any())
    <div class="alert-banner alert-banner-error" role="alert">
        <div class="alert-banner-icon">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
        </div>
        <div class="alert-banner-body">
            <p class="alert-banner-title">Aksi belum tersimpan</p>
            @foreach($errors->all() as $error)<p class="alert-banner-message">{{ $error }}</p>@endforeach
        </div>
    </div>
    @endif

    {{-- ══ Detail Panel — satu-satunya card di halaman (card budget = 1) ══ --}}
    <div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">

        {{-- Metric strip: kolom dengan divider, bukan card per metrik (§1.5) --}}
        <dl class="grid grid-cols-2 border-b border-slate-200 dark:border-slate-700 lg:grid-cols-4 [&>div]:border-slate-200 dark:[&>div]:border-slate-700">
            <div class="border-b border-r px-5 py-3.5 lg:border-b-0">
                <dt class="{{ $metricLabel }}">Jumlah percobaan</dt>
                <dd class="mt-1 font-mono text-lg font-semibold tabular-nums text-slate-900 dark:text-slate-50">
                    Percobaan {{ $attemptCount }}
                </dd>
                @if($isOpenCase)
                <dd class="mt-0.5 text-[11px] {{ $withinTolerance ? 'font-medium text-amber-700 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400' }}">
                    @if($withinTolerance)
                    Sudah {{ $tolerance }}× percobaan — boleh ditoleransi
                    @else
                    Ambang toleransi: {{ $tolerance }} percobaan
                    @endif
                </dd>
                @endif
            </div>
            <div class="border-b px-5 py-3.5 lg:border-b-0 lg:border-r">
                <dt class="{{ $metricLabel }}">Laporan terakhir</dt>
                <dd class="mt-1 font-mono text-[13px] font-semibold tabular-nums text-slate-900 dark:text-slate-50">{{ $latest?->created_at->translatedFormat('d M Y, H:i') ?? '-' }}</dd>
                <dd class="mt-0.5 truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $latest?->outcome->label() ?? '-' }}</dd>
            </div>
            <div class="border-r px-5 py-3.5">
                <dt class="{{ $metricLabel }}">Putus langganan</dt>
                <dd class="mt-1 font-mono text-[13px] font-semibold tabular-nums text-slate-900 dark:text-slate-50">
                    {{ $customer->terminated_at ? \App\Support\IndonesianDate::date($customer->terminated_at) : '-' }}
                </dd>
            </div>
            <div class="px-5 py-3.5">
                <dt class="{{ $metricLabel }}">Perangkat</dt>
                <dd class="mt-1 truncate font-mono text-[13px] font-semibold text-slate-900 dark:text-slate-50">{{ $device?->serial_number ?: '-' }}</dd>
                <dd class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                    @if($device?->retrieval_waived_at)
                    Dibebaskan {{ $device->retrieval_waived_at->translatedFormat('d M Y') }}
                    @elseif($device?->device_retrieved_at)
                    Diambil {{ $device->device_retrieved_at->translatedFormat('d M Y') }}
                    @else
                    Belum kembali ke gudang
                    @endif
                </dd>
            </div>
        </dl>

        {{-- Timeline percobaan: terbaru di atas --}}
        <div class="px-4 pt-5 pb-2 sm:px-6">
            <h2 class="mb-2 flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-[0.07em] text-slate-500 dark:text-slate-400">
                <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                Riwayat percobaan
            </h2>
            <ol>
                @foreach($attempts->reverse() as $index => $attempt)
                    @include('device_retrieval_reviews._attempt', [
                        'attempt' => $attempt,
                        'number' => $index + 1,
                        'isLast' => $loop->last,
                        'returnTo' => 'detail',
                    ])
                @endforeach
            </ol>
        </div>
    </div>
</div>

<x-ui.image-preview-modal />
@endsection
