@extends('layouts.app')

@section('title', 'Verifikasi Registrasi - '.$customer->full_name)
@section('page_title', 'Verifikasi Registrasi')
@section('breadcrumb_parent', 'Verifikasi Registrasi')
@section('breadcrumb_parent_url', route('customer-registration-verifications.index'))

@section('content')

{{-- HEADER HERO CARD --}}
<x-ui.card padding="comfortable" class="mb-6 shadow-sm border-l-4 border-l-amber-500">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Left: Customer Summary Info -->
        <div class="flex items-start sm:items-center gap-4">
            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl bg-amber-500/10 dark:bg-amber-400/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 flex items-center justify-center shrink-0 font-bold text-lg sm:text-xl shadow-xs">
                {{ strtoupper(substr($customer->full_name, 0, 1)) }}
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-lg sm:text-xl font-extrabold text-text-main tracking-tight">{{ $customer->full_name }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[11px] font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        {{ $customer->customer_code }}
                    </span>
                </div>
                <div class="flex items-center gap-2 mt-1.5 flex-wrap text-xs text-text-muted">
                    @if($customer->primary_phone)
                        <span class="flex items-center gap-1 font-mono text-text-secondary">
                            <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            {{ $customer->primary_phone }}
                        </span>
                        <span class="text-text-disabled">·</span>
                    @endif
                    <span class="flex items-center gap-1 font-medium text-text-secondary">
                        <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        {{ $customer->pop->name ?? 'POP Standard' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Right: Status Badge & Profile Detail Link -->
        <div class="flex items-center gap-2.5 sm:gap-3 flex-wrap">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold tracking-wide uppercase bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-900 shadow-2xs">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                Menunggu Verifikasi Registrasi
            </span>
            <x-ui.button variant="secondary" href="{{ route('customers.show', $customer) }}" class="text-xs font-semibold">
                Detail Profil →
            </x-ui.button>
        </div>
    </div>
</x-ui.card>

{{-- DATA REGISTRASI CONTENT CARD --}}
<x-ui.card padding="comfortable" class="mb-6 shadow-sm">
    @include('verifications.partials._registration-info')
</x-ui.card>

{{-- AKSI VALIDASI & CONTROL PANEL --}}
<x-ui.card padding="comfortable" class="shadow-sm" x-data="{ rejectOpen: false }">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2">
        <div>
            <h3 class="text-sm font-bold text-text-main uppercase tracking-wider">Aksi Validasi Registrasi</h3>
            <p class="text-xs text-text-muted mt-0.5">Tentukan keputusan verifikasi registrasi calon pelanggan ini.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            @can('customer_registration_verification.approve')
                <x-ui.button type="button" variant="primary" @click="$dispatch('open-modal', 'confirm-approve-modal')" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider px-4 py-2.5 shadow-sm">
                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Simpan Validasi Registrasi
                </x-ui.button>
            @endcan

            @can('customer_registration_verification.reject')
                <x-ui.button type="button" variant="secondary" @click="rejectOpen = ! rejectOpen" class="text-error border-error-border hover:bg-error-bg/60 text-xs font-bold uppercase tracking-wider px-4 py-2.5">
                    <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Tolak Registrasi
                </x-ui.button>
            @endcan
        </div>
    </div>

    {{-- Form Penolakan --}}
    @can('customer_registration_verification.reject')
        <form action="{{ route('customer-registration-verifications.reject', $customer) }}" method="POST"
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
            <textarea name="reason" rows="3" required maxlength="1000" placeholder="Jelaskan alasan penolakan registrasi ini..."
                      class="w-full text-xs sm:text-sm px-3.5 py-2.5 border border-rose-200 dark:border-rose-900 rounded-lg bg-surface text-text-main focus:outline-none focus:ring-2 focus:ring-rose-500/20 shadow-xs">{{ old('reason') }}</textarea>
            @error('reason')
                <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
            @enderror
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" @click="rejectOpen = false" class="px-3.5 py-2 text-xs font-semibold text-text-secondary hover:text-text-main border border-border rounded-lg bg-surface">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-sm transition-colors">
                    Konfirmasi Tolak Registrasi
                </button>
            </div>
        </form>
    @endcan
</x-ui.card>

{{-- MODAL KONFIRMASI APPROVE REGISTRASI --}}
@can('customer_registration_verification.approve')
    <x-ui.modal name="confirm-approve-modal" title="Konfirmasi Validasi Registrasi" maxWidth="md">
        <div class="space-y-3">
            <p class="text-sm text-text-secondary leading-relaxed">
                Apakah Anda yakin ingin menyetujui registrasi calon pelanggan <strong class="text-text-main font-bold">{{ $customer->full_name }}</strong>?
            </p>
            <x-ui.alert variant="info">
                Setelah disetujui, pelanggan akan masuk ke antrean survey dan diteruskan ke Task FOP untuk dijadwalkan oleh teknisi.
            </x-ui.alert>
        </div>
        <x-slot:footer>
            <form action="{{ route('customer-registration-verifications.approve', $customer) }}" method="POST" class="w-full sm:w-auto flex flex-col-reverse sm:flex-row gap-2 justify-end">
                @csrf
                @method('PUT')
                <button type="button" @click="$dispatch('close-modal', 'confirm-approve-modal')" class="px-4 py-2 text-xs font-semibold text-text-secondary border border-border rounded-lg hover:bg-surface-muted transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-sm transition-colors cursor-pointer">
                    Ya, Setujui Registrasi
                </button>
            </form>
        </x-slot:footer>
    </x-ui.modal>
@endcan

{{-- GLOBAL IMAGE PREVIEW MODAL COMPONENT --}}
<x-ui.image-preview-modal />

@endsection
