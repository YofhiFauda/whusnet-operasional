@extends('layouts.app')

@section('title', 'Verifikasi Registrasi - '.$customer->full_name)
@section('page_title', 'Verifikasi Registrasi')
@section('breadcrumb_parent', 'Verifikasi Registrasi')
@section('breadcrumb_parent_url', route('customer-registration-verifications.index'))

@section('content')
{{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

{{-- HEADER: Status Card Pelanggan — sama persis pola verifications/admin.blade.php
     (permintaan user 2026-09-16: dua halaman ini harus tampilan sama). --}}
<div class="bg-surface border border-border rounded-xl shadow-sm p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-100 dark:bg-amber-950 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-text-main">{{ $customer->full_name }}</h2>
                <div class="flex items-center gap-3 mt-1 flex-wrap">
                    <span class="text-xs font-mono text-text-muted">{{ $customer->customer_code }}</span>
                    <span class="text-text-disabled">·</span>
                    <span class="text-xs text-text-secondary">{{ $customer->primary_phone }}</span>
                    <span class="text-text-disabled">·</span>
                    <span class="text-xs text-text-secondary">{{ $customer->pop->name ?? '-' }}</span>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold tracking-wide uppercase bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-900">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                Menunggu Verifikasi Registrasi
            </span>
            <a href="{{ route('customers.show', $customer) }}" class="text-xs font-semibold text-text-secondary hover:text-primary transition-colors px-3 py-1.5 border border-border rounded-md hover:border-primary-border hover:bg-primary-soft">
                Detail Profil →
            </a>
        </div>
    </div>
</div>

{{-- Data Registrasi — partial SAMA PERSIS dipakai verifications/admin.blade.php
     tab "Data Registrasi", supaya tampilan dua halaman ini konsisten. Beda
     satu-satunya: di sini gak ada tab lain (Survey/Pemasangan/Pengujian)
     karena datanya memang belum ada sama sekali di tahap ini. --}}
<div class="bg-surface border border-border rounded-xl shadow-sm p-6 md:p-8 mb-6">
    @include('verifications.partials._registration-info')
</div>

{{-- Aksi lanjutan di halaman Detail miliknya sendiri → inline toggle Alpine,
     bukan modal/halaman baru (pola #3 CLAUDE.md). Satu x-data buat tombol
     Setujui + toggle Tolak. --}}
<div class="bg-surface border border-border rounded-xl shadow-sm p-6" x-data="{ rejectOpen: false }">
    <div class="flex flex-wrap items-center gap-3">
        @can('customer_registration_verification.approve')
        <form action="{{ route('customer-registration-verifications.approve', $customer) }}" method="POST" class="inline" onsubmit="return confirm('Setujui registrasi ini? Pelanggan akan masuk antrean survey dan muncul di Task FOP.')">
            @csrf
            @method('PUT')
            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-sm transition-colors cursor-pointer">
                Setujui — Masuk Antrean Survey
            </button>
        </form>
        @endcan

        @can('customer_registration_verification.reject')
        <button type="button" @click="rejectOpen = ! rejectOpen" class="px-4 py-2 bg-error-bg hover:bg-error-bg/80 text-error border border-error-border text-xs font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer">
            Tolak
        </button>
        @endcan
    </div>

    @can('customer_registration_verification.reject')
    <form action="{{ route('customer-registration-verifications.reject', $customer) }}" method="POST"
          x-show="rejectOpen" x-cloak
          class="mt-4 p-5 rounded-xl border border-error-border bg-error-bg/40 space-y-2">
        @csrf
        @method('PUT')
        <label class="block text-[11px] font-bold uppercase tracking-wider text-error">Alasan Penolakan <span class="text-error">*</span></label>
        <textarea name="reason" rows="2" required maxlength="1000" placeholder="Jelaskan alasan penolakan registrasi ini..."
                  class="w-full text-sm px-3 py-2 border border-error-border rounded-lg bg-surface text-text-main focus:outline-none focus:ring-2 focus:ring-error/20">{{ old('reason') }}</textarea>
        @error('reason')
            <p class="text-xs text-error">{{ $message }}</p>
        @enderror
        <button type="submit" class="px-4 py-2 bg-error hover:opacity-90 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-sm transition-colors cursor-pointer">Konfirmasi Tolak</button>
    </form>
    @endcan
</div>
@endsection
