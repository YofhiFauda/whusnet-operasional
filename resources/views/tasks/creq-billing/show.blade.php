@extends('layouts.app')

@section('title', 'Verifikasi Biaya C-REQ - '.$task->task_number)
@section('page_title', 'Verifikasi Biaya C-REQ')
@section('breadcrumb_parent', 'Verifikasi Biaya C-REQ')
@section('breadcrumb_parent_url', route('tasks.creq-billing.index'))

@php
    $detail = $task->creqDetail;
    // Kelas Tailwind FULL STATIC per status (bukan interpolasi
    // `bg-{{ $x }}-500`) — JIT scanner cuma mendeteksi string kelas utuh yang
    // benar-benar tertulis di file, pola sama TaskType::cardClasses().
    $statusHeroBorder = match($detail->verification_status->value) {
        'verified' => 'border-l-emerald-500',
        'rejected' => 'border-l-rose-500',
        default => 'border-l-amber-500',
    };
    $statusAvatarClasses = match($detail->verification_status->value) {
        'verified' => 'bg-emerald-500/10 dark:bg-emerald-400/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
        'rejected' => 'bg-rose-500/10 dark:bg-rose-400/10 text-rose-600 dark:text-rose-400 border-rose-500/30',
        default => 'bg-amber-500/10 dark:bg-amber-400/10 text-amber-600 dark:text-amber-400 border-amber-500/30',
    };
    $statusBadgeClasses = match($detail->verification_status->value) {
        'verified' => 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900',
        'rejected' => 'bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-900',
        default => 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-900',
    };
    $statusDotClass = match($detail->verification_status->value) {
        'verified' => 'bg-emerald-500',
        'rejected' => 'bg-rose-500',
        default => 'bg-amber-500',
    };
@endphp

@section('content')

{{-- HEADER HERO CARD --}}
<x-ui.card padding="comfortable" class="mb-6 shadow-sm border-l-4 {{ $statusHeroBorder }}">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-start sm:items-center gap-4">
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl {{ $statusAvatarClasses }} border flex items-center justify-center shrink-0 font-bold text-lg sm:text-xl shadow-xs">
                {{ strtoupper(substr($task->customer?->full_name ?? 'C', 0, 1)) }}
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-lg sm:text-xl font-extrabold text-text-main tracking-tight">{{ $task->customer?->full_name ?? 'Pelanggan tidak diketahui' }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[11px] font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        {{ $task->task_number }}
                    </span>
                </div>
                <div class="flex items-center gap-2 mt-1.5 flex-wrap text-xs text-text-muted">
                    <span class="flex items-center gap-1 font-medium text-text-secondary">{{ $task->pop?->name ?? 'POP Standard' }}</span>
                    <span class="text-text-disabled">·</span>
                    <span class="font-medium text-text-secondary">{{ $detail->category?->label() }}</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2.5 sm:gap-3 flex-wrap">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold tracking-wide uppercase border shadow-2xs {{ $statusBadgeClasses }}">
                <span class="w-2 h-2 rounded-full animate-pulse {{ $statusDotClass }}"></span>
                {{ $detail->verification_status->label() }}
            </span>
            <x-ui.button variant="secondary" href="{{ route('tasks.show', $task) }}" class="text-xs font-semibold">
                Detail Task →
            </x-ui.button>
        </div>
    </div>
</x-ui.card>

{{-- DATA C-REQ --}}
<x-ui.card padding="comfortable" class="mb-6 shadow-sm">
    <h3 class="text-sm font-bold text-text-main uppercase tracking-wider mb-4">Rincian Laporan C-REQ</h3>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs mb-4">
        <div class="bg-surface-muted border border-border rounded-xl p-3">
            <span class="block text-[9px] text-text-muted font-bold uppercase select-none">Kategori</span>
            <span class="font-bold text-text-main text-xs mt-1 block">{{ $detail->category?->label() }}</span>
        </div>
        @if($detail->category_custom_name)
        <div class="bg-surface-muted border border-border rounded-xl p-3">
            <span class="block text-[9px] text-text-muted font-bold uppercase select-none">Nama Kategori</span>
            <span class="font-bold text-text-main text-xs mt-1 block">{{ $detail->category_custom_name }}</span>
        </div>
        @endif
    </div>

    @if($detail->tikor_lama_lat || $detail->tikor_baru_lat)
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs mb-4">
        <div class="bg-surface-muted border border-border rounded-xl p-3">
            <span class="block text-[9px] text-text-muted font-bold uppercase select-none">Tikor Lama</span>
            <span class="font-mono text-text-main text-xs mt-1 block">{{ $detail->tikor_lama_lat ?? '-' }}, {{ $detail->tikor_lama_lng ?? '-' }}</span>
        </div>
        <div class="bg-surface-muted border border-border rounded-xl p-3">
            <span class="block text-[9px] text-text-muted font-bold uppercase select-none">Tikor Baru</span>
            <span class="font-mono text-text-main text-xs mt-1 block">{{ $detail->tikor_baru_lat ?? '-' }}, {{ $detail->tikor_baru_lng ?? '-' }}</span>
        </div>
    </div>
    @endif

    <div class="bg-surface-muted border border-border rounded-xl p-3 text-xs mb-4">
        <span class="block text-[9px] text-text-muted font-bold uppercase select-none">Catatan Biaya (dari Teknisi)</span>
        <p class="text-text-main text-xs mt-1 whitespace-pre-line">{{ $detail->billing_note ?: '-' }}</p>
    </div>

    @if($task->maintenanceReport)
    <div class="bg-surface-muted border border-border rounded-xl p-3 text-xs">
        <span class="block text-[9px] text-text-muted font-bold uppercase select-none">Kendala Teknis</span>
        <p class="text-text-main text-xs mt-1 whitespace-pre-line">{{ $task->maintenanceReport->kendala_teknis }}</p>
    </div>
    @endif

    @if($detail->verification_status->value === 'rejected' && $detail->rejection_reason)
    <div class="mt-4">
        <x-ui.alert variant="error">
            <span class="font-bold">Alasan Penolakan:</span> {{ $detail->rejection_reason }}
        </x-ui.alert>
    </div>
    @endif
</x-ui.card>

{{-- AKSI VERIFIKASI --}}
@if($detail->verification_status->value === 'pending')
<x-ui.card padding="comfortable" class="shadow-sm" x-data="{ rejectOpen: false }">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2">
        <div>
            <h3 class="text-sm font-bold text-text-main uppercase tracking-wider">Aksi Verifikasi Biaya</h3>
            <p class="text-xs text-text-muted mt-0.5">Setujui untuk lanjut ke Tagihan Manual, atau tolak kalau biayanya tidak valid.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            @can('creq_billing_verification.approve')
                <x-ui.button type="button" variant="primary" @click="$dispatch('open-modal', 'confirm-approve-modal')" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider px-4 py-2.5 shadow-sm">
                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Setujui &amp; Lanjut ke Tagihan Manual
                </x-ui.button>
            @endcan

            @can('creq_billing_verification.reject')
                <x-ui.button type="button" variant="secondary" @click="rejectOpen = ! rejectOpen" class="text-error border-error-border hover:bg-error-bg/60 text-xs font-bold uppercase tracking-wider px-4 py-2.5">
                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Tolak Biaya
                </x-ui.button>
            @endcan
        </div>
    </div>

    @can('creq_billing_verification.reject')
        <form action="{{ route('tasks.creq-billing.reject', $task) }}" method="POST"
              x-show="rejectOpen" x-cloak x-collapse
              class="mt-4 p-4 sm:p-5 rounded-xl border border-rose-200 dark:border-rose-900/60 bg-rose-50/50 dark:bg-rose-950/20 space-y-3">
            @csrf
            @method('PUT')
            <div class="flex items-center justify-between">
                <label class="block text-xs font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">
                    Alasan Penolakan <span class="text-rose-500">*</span>
                </label>
                <span class="text-[11px] text-text-muted">Maksimal 1000 karakter</span>
            </div>
            <textarea name="reason" rows="3" required maxlength="1000" placeholder="Jelaskan alasan penolakan biaya ini..."
                      class="w-full text-xs sm:text-sm px-3.5 py-2.5 border border-rose-200 dark:border-rose-900 rounded-lg bg-surface text-text-main focus:outline-none focus:ring-2 focus:ring-rose-500/20 shadow-xs">{{ old('reason') }}</textarea>
            @error('reason')
                <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
            @enderror
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" @click="rejectOpen = false" class="px-3.5 py-2 text-xs font-semibold text-text-secondary hover:text-text-main border border-border rounded-lg bg-surface">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-sm transition-colors">
                    Konfirmasi Tolak Biaya
                </button>
            </div>
        </form>
    @endcan
</x-ui.card>

{{-- MODAL KONFIRMASI APPROVE --}}
@can('creq_billing_verification.approve')
    <x-ui.modal name="confirm-approve-modal" title="Konfirmasi Persetujuan Biaya C-REQ" maxWidth="md">
        <div class="space-y-3">
            <p class="text-sm text-text-secondary leading-relaxed">
                Setujui biaya C-REQ task <strong class="text-text-main font-bold">{{ $task->task_number }}</strong>?
            </p>
            <x-ui.alert variant="info">
                Anda akan diarahkan ke form Tagihan Manual dengan pelanggan &amp; kategori sudah terisi — nominal tetap diisi manual.
            </x-ui.alert>
        </div>
        <x-slot:footer>
            <form action="{{ route('tasks.creq-billing.approve', $task) }}" method="POST" class="w-full sm:w-auto flex flex-col-reverse sm:flex-row gap-2 justify-end">
                @csrf
                @method('PUT')
                <button type="button" @click="$dispatch('close-modal', 'confirm-approve-modal')" class="px-4 py-2 text-xs font-semibold text-text-secondary border border-border rounded-lg hover:bg-surface-muted transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-sm transition-colors cursor-pointer">
                    Ya, Setujui &amp; Lanjut
                </button>
            </form>
        </x-slot:footer>
    </x-ui.modal>
@endcan
@endif

<x-ui.image-preview-modal />

@endsection
