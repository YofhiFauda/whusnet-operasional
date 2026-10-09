@extends('layouts.app')

@section('title', 'Verifikasi Registrasi - '.$customer->full_name)
@section('page_title', 'Verifikasi Registrasi')
@section('breadcrumb_parent', 'Verifikasi Registrasi')
@section('breadcrumb_parent_url', route('customer-registration-verifications.index'))

@section('content')

@php
    $service = $customer->customerService;
    $monthlyBill = $service?->total_monthly_bill ?? ($customer->internetPackage?->monthly_price ?? 0);
    $packageName = $service?->internetPackage?->name ?? ($customer->internetPackage?->name ?? ($service?->package_name_snapshot ?? '—'));
@endphp

<div x-data="{
    rejectOpen: false,
    copiedCode: false,
    copyCode(text) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            this.copiedCode = true;
            setTimeout(() => { this.copiedCode = false; }, 2000);
        });
    }
}">

    {{-- ── LAYER 1: PAGE HEADER (NAKED — NO CARD WRAPPER, SESUAI DESIGN.MD TYPE B) ── --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
        <div class="flex items-start sm:items-center gap-3">
            <a href="{{ route('customer-registration-verifications.index') }}"
               class="w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 flex items-center justify-center shrink-0 transition-colors shadow-2xs"
               title="Kembali ke Antrean Verifikasi">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>

            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        {{ $customer->full_name }}
                    </h1>
                    <button type="button"
                            @click="copyCode('{{ $customer->customer_code }}')"
                            class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-xs font-mono font-bold bg-slate-100 dark:bg-slate-800 text-sky-600 dark:text-sky-400 border border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                            :title="copiedCode ? 'Tersalin ke clipboard!' : 'Klik untuk salin ID REG'">
                        <span>{{ $customer->customer_code }}</span>
                        <template x-if="copiedCode">
                            <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        </template>
                        <template x-if="!copiedCode">
                            <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        </template>
                    </button>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/80 dark:border-amber-800/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                        Menunggu Verifikasi
                    </span>
                </div>
                <div class="flex items-center gap-2 mt-1 text-xs text-slate-500 dark:text-slate-400 flex-wrap">
                    <span>Terdaftar pada <strong class="font-mono text-slate-700 dark:text-slate-300">{{ \App\Support\IndonesianDate::dateTime($customer->created_at) }}</strong></span>
                    <span>·</span>
                    <span>POP: <strong class="text-slate-700 dark:text-slate-300">{{ $customer->pop?->name ?? '—' }}</strong></span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2.5 self-start md:self-center shrink-0">
            <a href="{{ route('customers.show', $customer) }}"
               class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors shadow-2xs">
                <span>Detail Profil Pelanggan</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
        </div>
    </div>

    {{-- ── LAYER 3: SINGLE CONTAINER PANEL (CARD BUDGET = 1 SESUAI DESIGN.MD TYPE B) ── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 rounded-lg shadow-2xs overflow-hidden mb-6">

        {{-- TOP METRIC STRIP (FLAT BAR WITH VERTICAL DIVIDERS) --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 border-b border-slate-200/80 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-900/30">
            {{-- Col 1: Paket --}}
            <div class="p-3.5 sm:p-4 border-r border-b lg:border-b-0 border-slate-200/60 dark:border-slate-700/50">
                <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    Paket Internet
                </span>
                <span class="block text-sm font-bold text-slate-900 dark:text-slate-100 mt-0.5 truncate" title="{{ $packageName }}">
                    {{ $packageName }}
                </span>
                @if($customer->internetPackage?->bandwidth_label)
                    <span class="inline-block mt-0.5 text-[10px] font-mono font-medium text-sky-600 dark:text-sky-400">
                        {{ $customer->internetPackage->bandwidth_label }}
                    </span>
                @endif
            </div>

            {{-- Col 2: Biaya Bulanan --}}
            <div class="p-3.5 sm:p-4 border-b lg:border-b-0 lg:border-r border-slate-200/60 dark:border-slate-700/50">
                <span class="block text-[10px] font-semibold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                    Tagihan Bulanan
                </span>
                <span class="block text-base font-mono font-bold text-sky-600 dark:text-sky-400 mt-0.5">
                    Rp {{ number_format($monthlyBill, 0, ',', '.') }}
                </span>
                <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                    Tarif paket bulanan
                </span>
            </div>

            {{-- Col 3: POP & Wilayah --}}
            <div class="p-3.5 sm:p-4 border-r border-b sm:border-b-0 border-slate-200/60 dark:border-slate-700/50">
                <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    POP &amp; Lokasi
                </span>
                <span class="block text-sm font-bold text-slate-800 dark:text-slate-200 mt-0.5 truncate">
                    {{ $customer->pop?->name ?? '—' }}
                </span>
                <span class="block text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                    {{ $customer->village ? 'Desa '.$customer->village->name : 'Alamat terdaftar' }}
                </span>
            </div>

            {{-- Col 4: Telepon --}}
            <div class="p-3.5 sm:p-4 border-r border-slate-200/60 dark:border-slate-700/50">
                <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    No. Telepon / WhatsApp
                </span>
                @if($customer->primary_phone)
                    <a href="https://wa.me/{{ preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $customer->primary_phone)) }}"
                       target="_blank"
                       class="inline-flex items-center gap-1 text-sm font-mono font-semibold text-emerald-600 dark:text-emerald-400 hover:underline mt-0.5">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.699c.971.53 1.771.815 2.796.815 3.181 0 5.767-2.586 5.768-5.766 0-3.18-2.587-5.767-5.768-5.767zm3.391 8.187c-.141.396-.713.729-1.002.775-.289.046-.657.067-1.077-.07-.42-.138-.97-.333-1.666-.636-1.579-.687-2.607-2.313-2.686-2.418-.079-.105-.644-.858-.644-1.636 0-.777.408-1.161.554-1.319.146-.158.32-.198.427-.198.106 0 .213.001.306.006.098.005.23-.037.36.275.136.326.464 1.134.505 1.218.041.084.068.182.014.29-.055.107-.082.174-.163.269-.082.095-.172.213-.246.286-.082.081-.168.17-.072.335.096.165.426.703.914 1.138.628.56 1.157.733 1.322.815.165.082.262.069.359-.043.097-.112.417-.487.528-.654.111-.167.223-.139.375-.083.153.056.969.457 1.136.541.167.084.278.125.32.195.041.069.041.402-.1.798z"/></svg>
                        {{ $customer->primary_phone }}
                    </a>
                @else
                    <span class="block text-sm text-slate-400 dark:text-slate-500 mt-0.5">-</span>
                @endif
                <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                    {{ $customer->email ?? 'Tanpa email' }}
                </span>
            </div>

            {{-- Col 5: Status Antrean --}}
            <div class="p-3.5 sm:p-4">
                <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                    Status Alur
                </span>
                <span class="block text-sm font-bold text-amber-600 dark:text-amber-400 mt-0.5">
                    Tahap 1: Verifikasi
                </span>
                <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">
                    Sebelum Task Survey
                </span>
            </div>
        </div>

        {{-- MAIN BODY AREA: DATA REGISTRASI CONTENT --}}
        <div class="p-5 sm:p-6">
            @include('verifications.partials._registration-info', [
                'canEditVerificationData' => auth()->user()->hasPermission('customer_registration_verification.approve'),
                'identityUpdateRoute' => 'customer-registration-verifications.update-identity',
                'packageUpdateRoute' => 'customer-registration-verifications.update-package',
                'verifCities' => $verifCities ?? [],
                'verifPackages' => $verifPackages ?? [],
            ])
        </div>

        {{-- ── BOTTOM DECISION & ACTION PANEL ── --}}
        <div class="border-t border-slate-200/80 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-900/40 p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                        KEPUTUSAN VERIFIKASI REGISTRASI
                    </span>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 mt-0.5">
                        Tentukan Keputusan Registrasi Calon Pelanggan
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-2xl">
                        Menyetujui akan otomatis menerbitkan Task &amp; FOP Task Survey dan meneruskan calon pelanggan ke teknisi lapangan. Menolak akan menghentikan pendaftaran.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    @can('customer_registration_verification.reject')
                        <button type="button"
                                @click="rejectOpen = ! rejectOpen"
                                class="inline-flex items-center gap-1.5 px-4 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg border border-rose-200 dark:border-rose-900/60 text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors cursor-pointer">
                            <svg class="w-4 h-4 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            <span>Tolak Registrasi</span>
                        </button>
                    @endcan

                    @can('customer_registration_verification.approve')
                        <button type="button"
                                @click="$dispatch('open-modal', 'confirm-approve-modal')"
                                class="inline-flex items-center gap-1.5 px-5 py-2.5 text-xs font-bold uppercase tracking-wider rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition-colors cursor-pointer">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span>Setujui Registrasi</span>
                        </button>
                    @endcan
                </div>
            </div>

            {{-- Collapsible Rejection Form --}}
            @can('customer_registration_verification.reject')
                <form action="{{ route('customer-registration-verifications.reject', $customer) }}" method="POST"
                      x-show="rejectOpen" x-cloak x-collapse
                      class="mt-4 p-4 sm:p-5 rounded-lg border border-rose-200/80 dark:border-rose-900/60 bg-rose-50/40 dark:bg-rose-950/20 space-y-3">
                    @csrf
                    @method('PUT')
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold uppercase tracking-wider text-rose-700 dark:text-rose-400">
                            Alasan Penolakan Registrasi <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[11px] text-slate-500 dark:text-slate-400">Maksimal 1000 karakter</span>
                    </div>
                    <textarea name="reason" rows="3" required maxlength="1000"
                              placeholder="Tuliskan alasan penolakan registrasi secara jelas (mis. alamat di luar coverage, data tidak valid, dll)..."
                              class="w-full text-xs sm:text-sm px-3.5 py-2.5 border border-rose-200 dark:border-rose-900 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-rose-500/20 shadow-2xs">{{ old('reason') }}</textarea>
                    @error('reason')
                        <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p>
                    @enderror
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" @click="rejectOpen = false"
                                class="px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 transition-colors">
                            Batal
                        </button>
                        <button type="submit"
                                class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-2xs transition-colors cursor-pointer">
                            Konfirmasi Tolak Registrasi
                        </button>
                    </div>
                </form>
            @endcan
        </div>
    </div>
</div>

{{-- MODAL KONFIRMASI APPROVE REGISTRASI --}}
@can('customer_registration_verification.approve')
    <x-ui.modal name="confirm-approve-modal" title="Konfirmasi Validasi Registrasi" maxWidth="md">
        <div class="space-y-3">
            <p class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                Apakah Anda yakin ingin menyetujui registrasi calon pelanggan <strong class="text-slate-900 dark:text-slate-100 font-bold">{{ $customer->full_name }}</strong>?
            </p>
            <div class="p-3.5 rounded-lg bg-sky-50/60 dark:bg-sky-950/30 border border-sky-200/80 dark:border-sky-900/50 text-slate-700 dark:text-slate-300 text-xs space-y-1">
                <div class="font-semibold text-sky-800 dark:text-sky-300">Dampak Persetujuan:</div>
                <ul class="list-disc list-inside space-y-0.5 text-slate-600 dark:text-slate-400">
                    <li>Sistem otomatis membuat <strong class="text-slate-800 dark:text-slate-200">Task Survey</strong> &amp; <strong class="text-slate-800 dark:text-slate-200">FOP Task</strong>.</li>
                    <li>Status alur calon pelanggan beralih ke <code class="font-mono text-sky-600 dark:text-sky-400">WAITING_SURVEY</code>.</li>
                    <li>Calon pelanggan akan muncul di antrean survey teknisi lapangan.</li>
                </ul>
            </div>
        </div>
        <x-slot:footer>
            <form action="{{ route('customer-registration-verifications.approve', $customer) }}" method="POST" class="w-full sm:w-auto flex flex-col-reverse sm:flex-row gap-2 justify-end">
                @csrf
                @method('PUT')
                <button type="button" @click="$dispatch('close-modal', 'confirm-approve-modal')" class="px-4 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                    Batal
                </button>
                <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-2xs transition-colors cursor-pointer">
                    Ya, Setujui Registrasi
                </button>
            </form>
        </x-slot:footer>
    </x-ui.modal>
@endcan

{{-- GLOBAL IMAGE PREVIEW MODAL COMPONENT --}}
<x-ui.image-preview-modal />

@endsection
