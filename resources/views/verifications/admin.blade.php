@extends('layouts.app')

@section('title', 'Verifikasi Admin - ' . $customer->full_name)
@section('page_title', 'Verifikasi Admin')

@section('content')
@php
    $status = $customer->status;
    $isSurveyStage = in_array($status, ['waiting_survey', 'survey_in_progress']);
    $isWaitingAccStage = in_array($status, ['waiting_acc', 'surveyed']);
    $isInstallationStage = in_array($status, ['waiting_installation', 'installation_in_progress', 'revision_installation']);
    $isVerifAdminStage = in_array($status, ['installed', 'verification_admin', 'active']);

    $showTabSurvey = !$isSurveyStage;
    $showTabPemasangan = !$isSurveyStage && !$isWaitingAccStage;
    $showTabPengujian = $isVerifAdminStage;
    $showTabVerifikasi = $isVerifAdminStage;

    $breadcrumbQueueName = $isSurveyStage ? 'Antrean Survey' : 'Antrean Verifikasi & Pemasangan';
    $breadcrumbQueueRoute = $isSurveyStage ? route('surveys.queue') : route('verifications.queue');

    $stageBadgeLabel = match(true) {
        $isSurveyStage => 'Antrean Survey',
        $isWaitingAccStage => 'Menunggu ACC Survey',
        $isInstallationStage => 'Proses Pemasangan',
        $isVerifAdminStage => 'Verifikasi Admin',
        default => Str::headline($status),
    };
@endphp

{{-- Breadcrumbs --}}
<div class="mb-6">
    <nav class="flex text-xs font-semibold text-slate-400 uppercase tracking-wider gap-2 items-center">
        <a href="{{ $breadcrumbQueueRoute }}" class="hover:text-slate-700 transition-colors">{{ $breadcrumbQueueName }}</a>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-slate-600">Detail Pelanggan</span>
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-slate-800 font-bold">{{ $customer->full_name }}</span>
    </nav>
</div>

{{-- Flash & Error messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

{{-- HEADER: Status Card Pelanggan --}}
<div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-900">{{ $customer->full_name }}</h2>
                <div class="flex items-center gap-3 mt-1 flex-wrap">
                    <span class="text-xs font-mono text-slate-500">{{ $customer->display_id }}</span>
                    <span class="text-slate-300">·</span>
                    <span class="text-xs text-slate-500">{{ $customer->primary_phone }}</span>
                    <span class="text-slate-300">·</span>
                    <span class="text-xs text-slate-500">{{ $customer->pop->name ?? '-' }}</span>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold tracking-wide uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                {{ $stageBadgeLabel }}
            </span>
            <a href="{{ route('customers.show', $customer) }}" class="text-xs font-semibold text-slate-500 hover:text-sky-600 transition-colors px-3 py-1.5 border border-slate-200 rounded-md hover:border-sky-200 hover:bg-sky-50">
                Detail Profil →
            </a>
        </div>
    </div>
</div>

        {{-- TAB NAVIGATION --}}
        <div class="mb-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                <div class="flex flex-wrap md:flex-nowrap border-b border-slate-200" id="tab-nav">
                    <button type="button" onclick="switchTab('registrasi')" id="tab-btn-registrasi"
                        class="tab-btn flex-1 px-4 py-4 text-sm font-semibold text-center border-b-2 border-sky-600 text-sky-700 bg-sky-50/60 transition-all focus:outline-none">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Data Registrasi
                        </span>
                    </button>
                    @if($showTabSurvey)
                    <button type="button" onclick="switchTab('survey')" id="tab-btn-survey"
                        class="tab-btn flex-1 px-4 py-4 text-sm font-semibold text-center border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition-all focus:outline-none">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            Survey
                        </span>
                    </button>
                    @endif
                    @if($showTabPemasangan)
                    <button type="button" onclick="switchTab('pemasangan')" id="tab-btn-pemasangan"
                        class="tab-btn flex-1 px-4 py-4 text-sm font-semibold text-center border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition-all focus:outline-none">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Pemasangan
                        </span>
                    </button>
                    @endif
                    @if($showTabPengujian)
                    <button type="button" onclick="switchTab('pengujian')" id="tab-btn-pengujian"
                        class="tab-btn flex-1 px-4 py-4 text-sm font-semibold text-center border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition-all focus:outline-none">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Pengujian
                        </span>
                    </button>
                    @endif
                    @if($showTabVerifikasi)
                    <button type="button" onclick="switchTab('verifikasi')" id="tab-btn-verifikasi"
                        class="tab-btn flex-1 px-4 py-4 text-sm font-semibold text-center border-b-2 border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50 transition-all focus:outline-none">
                        <span class="flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            Aktivasi
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[9px] font-bold bg-amber-100 text-amber-700 uppercase tracking-wide">Aksi</span>
                        </span>
                    </button>
                    @endif
                </div>

        {{-- ============================================================ --}}
        {{-- TAB 0: DATA REGISTRASI --}}
        {{-- ============================================================ --}}
        <div id="tab-registrasi" class="tab-panel p-6 md:p-8">
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                Informasi Registrasi Pelanggan
            </h4>
            
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Tanggal Registrasi</span>
                        <span class="block text-sm font-bold text-slate-800">{{ $customer->registration_date ? $customer->registration_date->format('d M Y') : '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Nama Lengkap</span>
                        <span class="block text-sm font-bold text-slate-800">{{ $customer->full_name }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Nomor Identitas (KTP/SIM)</span>
                        <span class="block text-sm font-mono text-slate-800">{{ $customer->identity_number ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Tipe Pelanggan</span>
                        <span class="block text-sm text-slate-800">{{ ucfirst($customer->customer_type ?? '-') }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Telepon Utama</span>
                        <span class="block text-sm font-mono text-slate-800">{{ $customer->primary_phone ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Email</span>
                        <span class="block text-sm text-slate-800">{{ $customer->email ?? '-' }}</span>
                    </div>
                    <div class="md:col-span-3">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Alamat Pemasangan</span>
                        <span class="block text-sm text-slate-800">
                            {{ $customer->address }}
                            @if($customer->village)
                                <br><span class="text-xs text-slate-500">Kel/Desa. {{ $customer->village->name }}, Kec. {{ $customer->village->district->name ?? '-' }}, {{ $customer->city->name ?? '-' }}</span>
                            @endif
                        </span>
                    </div>
                    @if($customer->latitude && $customer->longitude)
                    <div class="md:col-span-3">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Koordinat (Latitude, Longitude)</span>
                        <a href="https://maps.google.com/?q={{ $customer->latitude }},{{ $customer->longitude }}" target="_blank" class="text-sm font-mono text-sky-600 hover:underline flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            {{ $customer->latitude }}, {{ $customer->longitude }}
                        </a>
                    </div>
                    @endif
                </div>
            </div>
            
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                Layanan Terpilih
            </h4>
            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-5 mb-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-indigo-400 mb-1">Paket Internet</span>
                        <span class="block text-sm font-bold text-indigo-900">{{ $customer->internetPackage->name ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-indigo-400 mb-1">Biaya Berlangganan</span>
                        <span class="block text-sm font-mono font-bold text-indigo-900">Rp {{ number_format($customer->customerService->total_monthly_bill ?? 0, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            {{-- Dokumen Foto Pelanggan --}}
            <div class="mb-6">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Dokumen & Foto
                </h4>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @if($customer->foto_ktp)
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center">
                        <span class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Foto KTP</span>
                        <img src="{{ asset('storage/' . $customer->foto_ktp) }}" alt="KTP" class="h-32 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90" onclick="openPhotoLightbox('{{ asset('storage/' . $customer->foto_ktp) }}', 'Foto KTP')">
                    </div>
                    @endif
                    @if($customer->foto_rumah)
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center">
                        <span class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Foto Rumah</span>
                        <img src="{{ asset('storage/' . $customer->foto_rumah) }}" alt="Rumah" class="h-32 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90" onclick="openPhotoLightbox('{{ asset('storage/' . $customer->foto_rumah) }}', 'Foto Rumah')">
                    </div>
                    @endif
                    @if($customer->foto_kontrak)
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center">
                        <span class="block text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">Foto Kontrak</span>
                        <img src="{{ asset('storage/' . $customer->foto_kontrak) }}" alt="Kontrak" class="h-32 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90" onclick="openPhotoLightbox('{{ asset('storage/' . $customer->foto_kontrak) }}', 'Foto Kontrak')">
                    </div>
                    @endif
                    @if(!$customer->foto_ktp && !$customer->foto_rumah && !$customer->foto_kontrak)
                    <div class="col-span-3 bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center gap-3">
                        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <p class="text-sm text-amber-800">Tidak ada dokumen atau foto yang diunggah saat registrasi.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- TAB: SURVEY --}}
        {{-- ============================================================ --}}
        <div id="tab-survey" class="tab-panel p-6 md:p-8 hidden">
            @php
                $survey = $customer->latestSurvey;
            @endphp

            @if($survey)
            <div class="mb-6 bg-sky-50 border border-sky-200 rounded-xl p-5">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-sky-600 mb-1">Status Survey</span>
                        @php
                            $sStatus = $survey->survey_status ?? 'pending';
                            $sc = ['completed' => 'bg-emerald-100 text-emerald-800 border-emerald-200', 'failed' => 'bg-red-100 text-red-800 border-red-200', 'in_progress' => 'bg-sky-100 text-sky-800 border-sky-200', 'scheduled' => 'bg-slate-100 text-slate-800 border-slate-200'];
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wide border {{ $sc[$sStatus] ?? $sc['scheduled'] }}">
                            {{ ucfirst($sStatus) }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-sky-600 mb-1">Mulai Survey</span>
                        <span class="block text-sm font-mono font-bold text-slate-800">
                            {{ $survey->started_at ? $survey->started_at->format('d M Y, H:i') : '-' }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-sky-600 mb-1">Selesai Survey</span>
                        <span class="block text-sm font-mono font-bold text-slate-800">
                            {{ $survey->completed_at ? $survey->completed_at->format('d M Y, H:i') : '-' }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-sky-600 mb-1">Durasi Survey</span>
                        <span class="block text-sm font-mono font-bold text-emerald-700">
                            {{ $survey->duration_minutes ? $survey->duration_minutes . ' Menit' : '-' }}
                        </span>
                    </div>
                </div>
            </div>

            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Tim Surveyor
            </h4>
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Teknisi Utama</span>
                        <span class="block text-sm font-bold text-slate-800">{{ $survey->technician->name ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Surveyor 2</span>
                        <span class="block text-sm text-slate-800">{{ $survey->surveyor2->name ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Surveyor 3</span>
                        <span class="block text-sm text-slate-800">{{ $survey->surveyor3->name ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Hasil Survey Teknis
            </h4>
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">ODP Terdekat</span>
                        <span class="block text-sm font-mono font-bold text-slate-800">{{ $survey->nearest_odp ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Estimasi Kabel (Meter)</span>
                        <span class="block text-sm font-mono text-slate-800">{{ $survey->cable_estimation_meter ?? '-' }} Meter</span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Kebutuhan FOP / Tiang</span>
                        <span class="block text-sm text-slate-800">{{ $survey->fop_id ?? '-' }}</span>
                    </div>
                    <div class="md:col-span-3">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Alat Tambahan / Kebutuhan Material</span>
                        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ $survey->required_tools ?? '-' }}</p>
                    </div>
                    @if($survey->survey_note)
                    <div class="md:col-span-3 pt-4 border-t border-slate-200 mt-2">
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Catatan Surveyor</span>
                        <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ $survey->survey_note }}</p>
                    </div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @if($survey->survey_photo)
                <div>
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Foto Lokasi / Survey</h4>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center inline-block w-full">
                        <img src="{{ asset('storage/' . $survey->survey_photo) }}" alt="Foto Survey" class="max-h-48 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90" onclick="openPhotoLightbox('{{ asset('storage/' . $survey->survey_photo) }}', 'Foto Survey')">
                    </div>
                </div>
                @endif
                @if($survey->house_photo)
                <div>
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Foto Rumah</h4>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center inline-block w-full">
                        <img src="{{ asset('storage/' . $survey->house_photo) }}" alt="Foto Rumah" class="max-h-48 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90" onclick="openPhotoLightbox('{{ asset('storage/' . $survey->house_photo) }}', 'Foto Rumah')">
                    </div>
                </div>
                @endif
            </div>

            @if($isWaitingAccStage)
                @can('customers.detail.installation.validate')
                <div class="mt-6 p-5 bg-amber-50 border border-amber-200 rounded-xl flex flex-col md:flex-row items-center justify-between gap-4">
                    <div>
                        <h5 class="text-sm font-bold text-amber-900">Persetujuan Hasil Survey</h5>
                        <p class="text-xs text-amber-700 mt-0.5">Survey telah selesai dilaporkan. Periksa data registrasi dan hasil survey di atas, lalu tentukan keputusan persetujuan.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="openRejectModal()" class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 text-xs font-bold uppercase tracking-wider rounded-lg transition-colors cursor-pointer">
                            Batalkan / Gagal
                        </button>
                        <form action="{{ route('customers.verification.process-to-team', $customer) }}" method="POST" onsubmit="event.preventDefault(); window.confirmAction('Setujui hasil survey dan proses pelanggan ini ke tim pemasangan?', this);">
                            @csrf
                            <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-sm transition-colors cursor-pointer">
                                Setujui & Proses ke Tim Pemasangan
                            </button>
                        </form>
                    </div>
                </div>
                @endcan
            @endif

            @else
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-center gap-3">
                <svg class="w-6 h-6 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <p class="text-sm text-amber-800">Tidak ada data laporan survey untuk pelanggan ini.</p>
            </div>
            @endif
        </div>

        {{-- ============================================================ --}}
        {{-- TAB 1: PROSES PEMASANGAN --}}
        {{-- ============================================================ --}}
        <div id="tab-pemasangan" class="tab-panel p-6 md:p-8 hidden">

            @php
                $installation = $customer->latestInstallation;
                $device = $customer->customerDevice;
                $techDetail = $customer->customerTechnicalDetail;
            @endphp

            {{-- Durasi Pemasangan --}}
            @if($installation)
            <div class="mb-6 bg-sky-50 border border-sky-200 rounded-xl p-5">
                <div class="flex flex-wrap gap-6">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-sky-600 mb-1">Mulai Pemasangan</span>
                        <span class="block text-sm font-mono font-bold text-slate-800">
                            {{ $installation->started_at ? $installation->started_at->format('d M Y, H:i') : '-' }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-sky-600 mb-1">Selesai Pemasangan</span>
                        <span class="block text-sm font-mono font-bold text-slate-800">
                            {{ $installation->completed_at ? $installation->completed_at->format('d M Y, H:i') : '-' }}
                        </span>
                    </div>
                    @if($installation->started_at && $installation->completed_at)
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-sky-600 mb-1">Durasi (SLA)</span>
                        @php
                            $duration = $installation->started_at->diff($installation->completed_at);
                        @endphp
                        <span class="block text-sm font-mono font-bold text-emerald-700">
                            {{ $duration->h }}j {{ $duration->i }}m {{ $duration->s }}d
                        </span>
                    </div>
                    @endif
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-sky-600 mb-1">Status Pemasangan</span>
                        @php
                            $statusMap = ['completed' => ['label' => 'Selesai', 'class' => 'bg-emerald-50 text-emerald-700 border-emerald-200'], 'failed' => ['label' => 'Gagal', 'class' => 'bg-red-50 text-red-700 border-red-200'], 'in_progress' => ['label' => 'Proses', 'class' => 'bg-sky-50 text-sky-700 border-sky-200'], 'scheduled' => ['label' => 'Terjadwal', 'class' => 'bg-slate-50 text-slate-700 border-slate-200']];
                            $statusInfo = $statusMap[$installation->installation_status] ?? ['label' => ucfirst($installation->installation_status ?? '-'), 'class' => 'bg-slate-50 text-slate-700 border-slate-200'];
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wide border {{ $statusInfo['class'] }}">
                            {{ $statusInfo['label'] }}
                        </span>
                    </div>
                </div>
                @if($installation->installation_note)
                <div class="mt-4 pt-4 border-t border-sky-200">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-sky-600 mb-1">Catatan Pemasangan</span>
                    <p class="text-sm text-slate-700 whitespace-pre-wrap">{{ $installation->installation_note }}</p>
                </div>
                @endif
            </div>
            @else
            <div class="mb-6 bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center gap-3">
                <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <p class="text-sm text-amber-800">Data riwayat pemasangan tidak ditemukan untuk pelanggan ini.</p>
            </div>
            @endif

            {{-- DATA PERANGKAT --}}
            <div class="mb-6">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2v-4M9 21H5a2 2 0 01-2-2v-4m0 0h18"/></svg>
                    Data Perangkat
                </h4>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-5">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-5">
                        @php
                            $deviceFields = [
                                ['label' => 'Jenis Perangkat', 'value' => strtoupper($device->device_type ?? '-')],
                                ['label' => 'Mode Koneksi', 'value' => strtoupper($device->connection_mode ?? '-')],
                                ['label' => 'Merk', 'value' => $device->brand ?? '-'],
                                ['label' => 'Tipe / Model', 'value' => $device->model ?? '-'],
                                ['label' => 'Serial Number', 'value' => $device->serial_number ?? '-', 'mono' => true],
                                ['label' => 'MAC Address', 'value' => $device->mac_address ?? '-', 'mono' => true],
                                ['label' => 'Username PPPoE', 'value' => $device->pppoe_username ?? '-', 'mono' => true],
                                ['label' => 'Password PPPoE', 'value' => $device->pppoe_password ?? '-', 'mono' => true],
                                ['label' => 'SSID WiFi', 'value' => $device->wifi_ssid ?? ($techDetail->ssid ?? '-')],
                                ['label' => 'Password WiFi', 'value' => $device->wifi_password ?? '-'],
                                ['label' => 'IP Address', 'value' => $device->ip_address ?? '-', 'mono' => true],
                            ];
                        @endphp
                        @foreach($deviceFields as $field)
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">{{ $field['label'] }}</span>
                            <span class="block text-sm {{ ($field['mono'] ?? false) ? 'font-mono' : '' }} text-slate-800 font-medium">
                                {{ $field['value'] }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- DATA JARINGAN / ODP / OLT --}}
            <div class="mb-6">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.14 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"/></svg>
                    Informasi Distribusi Jaringan (ODP / OLT)
                </h4>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-5">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-5">
                        @php
                            $netFields = [
                                ['label' => 'Nomor ODP', 'value' => $techDetail->odp_number ?? '-', 'mono' => true],
                                ['label' => 'Port ODP', 'value' => $techDetail->odp_port ?? '-', 'mono' => true],
                                ['label' => 'Nomor OLT', 'value' => $techDetail->olt_number ?? '-', 'mono' => true],
                                ['label' => 'Slot OLT', 'value' => $techDetail->olt_slot ?? '-', 'mono' => true],
                                ['label' => 'Port OLT', 'value' => $techDetail->olt_port ?? '-', 'mono' => true],
                                ['label' => 'VLAN', 'value' => $techDetail->vlan ?? '-', 'mono' => true],
                                ['label' => 'Nomor Router', 'value' => $techDetail->router_number ?? '-', 'mono' => true],
                                ['label' => 'Redaman Awal (dBm)', 'value' => $techDetail->initial_attenuation ?? '-', 'mono' => true],
                            ];
                        @endphp
                        @foreach($netFields as $field)
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">{{ $field['label'] }}</span>
                            <span class="block text-sm {{ ($field['mono'] ?? false) ? 'font-mono' : '' }} text-slate-800 font-medium">
                                {{ $field['value'] }}
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Foto Pemasangan, Kontrak & TTD --}}
            @if($installation)
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                @if($installation->installation_photo)
                <div>
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Foto Pemasangan</h4>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center">
                        <img src="{{ asset('storage/' . $installation->installation_photo) }}" 
                             alt="Foto Pemasangan" 
                             class="h-32 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90 transition-opacity"
                             onclick="openPhotoLightbox('{{ asset('storage/' . $installation->installation_photo) }}', 'Foto Pemasangan')">
                    </div>
                </div>
                @endif
                @if($installation->contract_photo)
                <div>
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Foto Kontrak</h4>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center">
                        <img src="{{ asset('storage/' . $installation->contract_photo) }}" 
                             alt="Foto Kontrak" 
                             class="h-32 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90 transition-opacity"
                             onclick="openPhotoLightbox('{{ asset('storage/' . $installation->contract_photo) }}', 'Foto Kontrak')">
                    </div>
                </div>
                @endif
                @if($installation->signature_photo)
                <div>
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Foto TTD Pelanggan</h4>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center">
                        <img src="{{ asset('storage/' . $installation->signature_photo) }}" 
                             alt="Foto TTD Pelanggan" 
                             class="h-32 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90 transition-opacity"
                             onclick="openPhotoLightbox('{{ asset('storage/' . $installation->signature_photo) }}', 'Foto TTD Pelanggan')">
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>

        {{-- ============================================================ --}}
        {{-- TAB 2: PENGUJIAN --}}
        {{-- ============================================================ --}}
        <div id="tab-pengujian" class="tab-panel p-6 md:p-8 hidden">

            @if($techDetail)
            {{-- Speed Metrics --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                @php
                    $package = $customer->internetPackage;
                    $downloadSpeed = $techDetail->test_download;
                    $uploadSpeed = $techDetail->test_upload;
                    $conformity = $techDetail->speed_conformity_percent;

                    $downloadColor = $conformity >= 90 ? 'emerald' : ($conformity >= 70 ? 'amber' : 'red');
                @endphp

                <div class="bg-sky-50 border border-sky-200 rounded-xl p-4 text-center">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-sky-600 mb-1">Download</span>
                    <span class="block text-2xl font-extrabold font-mono text-sky-700">{{ $downloadSpeed ?? '-' }}</span>
                    <span class="block text-xs text-slate-400 mt-1">Mbps</span>
                </div>

                <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 text-center">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-indigo-600 mb-1">Upload</span>
                    <span class="block text-2xl font-extrabold font-mono text-indigo-700">{{ $uploadSpeed ?? '-' }}</span>
                    <span class="block text-xs text-slate-400 mt-1">Mbps</span>
                </div>

                <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-center">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-amber-600 mb-1">Latency</span>
                    <span class="block text-2xl font-extrabold font-mono text-amber-700">{{ $techDetail->latency_ms ?? '-' }}</span>
                    <span class="block text-xs text-slate-400 mt-1">ms</span>
                </div>

                <div class="bg-purple-50 border border-purple-200 rounded-xl p-4 text-center">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-purple-600 mb-1">Jitter</span>
                    <span class="block text-2xl font-extrabold font-mono text-purple-700">{{ $techDetail->jitter_ms ?? '-' }}</span>
                    <span class="block text-xs text-slate-400 mt-1">ms</span>
                </div>
            </div>

            {{-- Detail Kualitas --}}
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-5 mb-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-5">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Packet Loss</span>
                        <span class="block text-sm font-mono font-bold {{ ($techDetail->packet_loss_percent ?? 0) <= 1 ? 'text-emerald-700' : 'text-red-700' }}">
                            {{ $techDetail->packet_loss_percent !== null ? $techDetail->packet_loss_percent . '%' : '-' }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Redaman Aktual (dBm)</span>
                        <span class="block text-sm font-mono font-bold text-slate-800">{{ $techDetail->actual_attenuation ?? '-' }}</span>
                    </div>
                    @if($package)
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Paket (Target)</span>
                        <span class="block text-sm font-bold text-slate-800">{{ $package->name }} ({{ $package->download_speed_mbps ?? '-' }} Mbps)</span>
                    </div>
                    @endif
                    @if($conformity !== null)
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">% Sesuai Paket</span>
                        <span class="block text-xl font-extrabold font-mono {{ $downloadColor === 'emerald' ? 'text-emerald-700' : ($downloadColor === 'amber' ? 'text-amber-600' : 'text-red-600') }}">
                            {{ number_format($conformity, 1) }}%
                        </span>
                    </div>
                    @endif
                </div>

                {{-- Conformity Bar --}}
                @if($conformity !== null)
                <div class="mt-4 pt-4 border-t border-slate-200">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Kesesuaian Kecepatan Paket</span>
                        <span class="text-xs font-bold font-mono {{ $downloadColor === 'emerald' ? 'text-emerald-600' : ($downloadColor === 'amber' ? 'text-amber-600' : 'text-red-600') }}">{{ number_format($conformity, 1) }}%</span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-2.5 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700 {{ $downloadColor === 'emerald' ? 'bg-emerald-500' : ($downloadColor === 'amber' ? 'bg-amber-400' : 'bg-red-500') }}"
                             style="width: {{ min(100, $conformity) }}%">
                        </div>
                    </div>
                    <div class="flex justify-between text-[9px] text-slate-400 mt-1">
                        <span>0%</span>
                        <span class="text-amber-500">70% (Min)</span>
                        <span class="text-emerald-500">90% (Ideal)</span>
                        <span>100%</span>
                    </div>
                </div>
                @endif
            </div>

            {{-- Foto Speedtest --}}
            @if($techDetail->speedtest_photo)
            <div>
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Foto Hasil Speedtest</h4>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 inline-block">
                    <img src="{{ asset('storage/' . $techDetail->speedtest_photo) }}"
                         alt="Foto Speedtest"
                         class="max-h-64 max-w-full rounded-lg object-contain border border-slate-200 shadow-sm cursor-pointer hover:opacity-90 transition-opacity"
                         onclick="openPhotoLightbox('{{ asset('storage/' . $techDetail->speedtest_photo) }}', 'Foto Speedtest')">
                </div>
            </div>
            @endif

            @else
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 flex items-center gap-3">
                <svg class="w-6 h-6 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <p class="text-sm text-amber-800">Data pengujian (speedtest) belum tersedia untuk pelanggan ini. Pastikan teknisi telah mengisi laporan pemasangan dengan hasil speedtest.</p>
            </div>
            @endif
        </div>

        {{-- ============================================================ --}}
        {{-- TAB 3: VERIFIKASI & AKTIVASI --}}
        {{-- ============================================================ --}}
        <div id="tab-verifikasi" class="tab-panel p-6 md:p-8 hidden">

            @php
                $service = $customer->customerService;
            @endphp

            {{-- Info notifikasi --}}
            <div class="mb-6 bg-emerald-50 border border-emerald-200 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="text-sm font-semibold text-emerald-800">Langkah Terakhir: Verifikasi & Aktivasi Pelanggan</p>
                        <p class="text-xs text-emerald-700 mt-1">Periksa kembali data pemasangan dan pengujian di tab sebelumnya, kemudian isi form di bawah ini untuk mengaktifkan pelanggan dan menerbitkan tagihan pertama.</p>
                    </div>
                </div>
            </div>

            {{-- Ringkasan Data Layanan --}}
            @if($service)
            <div class="mb-6">
                <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Ringkasan Layanan</h4>
                <div class="bg-slate-50 border border-slate-200 rounded-xl p-5">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-5">
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Paket Internet</span>
                            <span class="block text-sm font-bold text-slate-800">{{ $customer->internetPackage->name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Biaya Bulanan</span>
                            <span class="block text-sm font-mono font-bold text-slate-800">Rp {{ number_format($service->total_monthly_bill ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">Diskon</span>
                            <span class="block text-sm font-mono text-slate-800">Rp {{ number_format($service->discount ?? 0, 0, ',', '.') }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1">PPN</span>
                            <span class="block text-sm font-mono text-slate-800">{{ number_format($service->ppn ?? 0, 0, ',', '.') }}%</span>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            {{-- FORM VERIFIKASI --}}


            <form id="verifyForm" method="POST" action="{{ route('customers.verification.final', $customer) }}" class="space-y-6">
                @csrf

                <div>
                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Form Penerbitan Tagihan Pertama</h4>
                    <div class="bg-white border border-slate-200 rounded-xl p-6 space-y-5 shadow-sm">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">
                            <div>
                                <label for="billing_period" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">PERIODE TAGIHAN <span class="text-red-500">*</span></label>
                                <input type="month" name="billing_period" id="billing_period"
                                    class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-white"
                                    required value="{{ old('billing_period', date('Y-m')) }}">
                            </div>
                            <div>
                                <label for="issue_date" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">TANGGAL AKTIVASI / TERBIT <span class="text-red-500">*</span></label>
                                <input type="date" name="issue_date" id="issue_date"
                                    class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-white"
                                    required value="{{ old('issue_date', date('Y-m-d')) }}" onchange="calculateFees()">
                                <p class="text-[10px] text-slate-400 mt-1">Tanggal ini digunakan untuk menghitung tagihan Prorate.</p>
                            </div>
                        </div>

                        <div class="mb-5">
                            <label for="due_date" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">JATUH TEMPO <span class="text-red-500">*</span></label>
                            <input type="date" name="due_date" id="due_date"
                                class="w-full text-sm px-3 py-2.5 border border-slate-200 rounded-lg focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 bg-white"
                                required value="{{ old('due_date', date('Y-m-d', strtotime('+7 days'))) }}">
                        </div>

                        <div class="border-t border-slate-100 pt-5">
                            <h5 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4">Rincian Tagihan & Biaya Pemasangan</h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="subtotal" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 flex justify-between">
                                        <span>SUBTOTAL (PRORATA + BIAYA)</span>
                                        <span class="text-[9px] text-slate-400 font-normal">Harga paket Rp {{ number_format($service->monthly_price ?? 0, 0, ',', '.') }}/bln</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-medium">Rp</span>
                                        <input type="number" step="0.01" name="subtotal" id="fv_subtotal"
                                            class="w-full pl-9 text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-slate-50 font-mono text-slate-700"
                                            value="{{ old('subtotal', $service->monthly_price ?? 0) }}" readonly>
                                        {{-- Basis prorata pakai harga paket SEBELUM PPN; PPN ditambahkan
                                             sekali di akhir, bukan ikut terbawa di basis. --}}
                                        <input type="hidden" id="base_monthly_bill" value="{{ $service->monthly_price ?? 0 }}">
                                    </div>
                                </div>
                                <div>
                                    <label for="prorate_amount" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 flex justify-between">
                                        <span>TAGIHAN PRORATE <span class="text-red-500">*</span></span>
                                        <span id="prorate_info" class="text-[9px] text-emerald-600 font-normal">S/d akhir bulan</span>
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-medium">Rp</span>
                                        <input type="number" step="0.01" name="prorate_amount" id="fv_prorate_amount"
                                            class="w-full pl-9 text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-slate-50 font-mono text-slate-700"
                                            value="{{ old('prorate_amount', 0) }}" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div>
                                    <label for="extra_installation_fee" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">BIAYA PEMASANGAN</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-medium">Rp</span>
                                        <input type="number" step="0.01" name="extra_installation_fee" id="fv_extra_installation_fee"
                                            class="w-full pl-9 text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white font-mono text-slate-700 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                            value="{{ old('extra_installation_fee', 0) }}" onkeyup="calculateFees()" onchange="calculateFees()">
                                    </div>
                                </div>
                                <div>
                                    <label for="extra_cable_fee" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">KABEL TAMBAHAN</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-medium">Rp</span>
                                        <input type="number" step="0.01" name="extra_cable_fee" id="fv_extra_cable_fee"
                                            class="w-full pl-9 text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white font-mono text-slate-700 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                            value="{{ old('extra_cable_fee', 0) }}" onkeyup="calculateFees()" onchange="calculateFees()">
                                    </div>
                                </div>
                                <div>
                                    <label for="extra_pole_fee" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">TAMBAHAN TIANG</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-medium">Rp</span>
                                        <input type="number" step="0.01" name="extra_pole_fee" id="fv_extra_pole_fee"
                                            class="w-full pl-9 text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-white font-mono text-slate-700 focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500"
                                            value="{{ old('extra_pole_fee', 0) }}" onkeyup="calculateFees()" onchange="calculateFees()">
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="discount" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">DISKON</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-medium">Rp</span>
                                        <input type="number" step="0.01" name="discount" id="fv_discount"
                                            class="w-full pl-9 text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-slate-50 font-mono text-slate-700"
                                            value="{{ old('discount', $service->discount ?? 0) }}" readonly>
                                    </div>
                                </div>
                                <div>
                                    <label for="ppn" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">PPN (%)</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm font-medium">%</span>
                                        <input type="number" step="0.01" name="ppn" id="fv_ppn"
                                            class="w-full pl-9 text-sm px-3 py-2.5 border border-slate-200 rounded-lg bg-slate-50 font-mono text-slate-700"
                                            value="{{ old('ppn', $service->ppn ?? 0) }}" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 p-4 bg-sky-50 border border-sky-200 rounded-xl">
                                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">TOTAL TAGIHAN (PRORATE + BIAYA)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sky-700 text-base font-extrabold">Rp</span>
                                    <input type="number" step="0.01" name="total_amount" id="fv_total_amount"
                                        class="w-full pl-10 text-xl font-extrabold font-mono py-3 px-3 border border-sky-300 rounded-lg bg-sky-50 text-sky-700 focus:outline-none focus:border-sky-500"
                                        value="{{ old('total_amount', 0) }}" readonly>
                                </div>
                            </div>
                        </div>

                        {{-- Tombol Aksi --}}
                        <div class="pt-5 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-4">
                            <a href="{{ route('verifications.queue') }}"
                                class="text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors px-4 py-2 border border-slate-200 rounded-lg hover:bg-slate-50">
                                ← Kembali ke Antrean
                            </a>
                            <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                                <button type="button" onclick="openRejectModal()" class="flex justify-center items-center gap-2 px-6 py-2.5 text-sm font-bold text-red-700 bg-white border border-red-300 rounded-lg hover:bg-red-50 transition-all shadow-sm active:scale-95 cursor-pointer focus:outline-none focus:ring-2 focus:ring-red-500/30">
                                    Tolak
                                </button>
                                <button type="button" onclick="openRevisiModal()" class="flex justify-center items-center gap-2 px-6 py-2.5 text-sm font-bold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-all shadow-sm active:scale-95 cursor-pointer focus:outline-none focus:ring-2 focus:ring-slate-500/30">
                                    Revisi Pemasangan
                                </button>
                                <button type="submit" id="btn-activate"
                                    class="flex justify-center items-center gap-2 px-6 py-2.5 text-sm font-bold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700 transition-all shadow-sm hover:shadow-md active:scale-95 cursor-pointer focus:outline-none focus:ring-2 focus:ring-emerald-500/30">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Aktivasi & Terbitkan Tagihan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- LIGHTBOX FOTO --}}
<div id="photo-lightbox" class="fixed inset-0 z-[200] hidden items-center justify-center bg-black/80 backdrop-blur-sm" onclick="closePhotoLightbox()">
    <div class="relative max-w-3xl max-h-[85vh] mx-4">
        <button type="button" onclick="closePhotoLightbox()" class="absolute -top-10 right-0 text-white text-2xl font-bold hover:text-slate-300 transition-colors focus:outline-none">✕</button>
        <img id="lightbox-img" src="" alt="" class="max-w-full max-h-[80vh] object-contain rounded-lg shadow-2xl border border-white/10">
        <p id="lightbox-caption" class="text-center text-white text-xs font-semibold mt-3 uppercase tracking-wider"></p>
    </div>
</div>

{{-- MODAL REVISI PEMASANGAN --}}
<div id="revisiModal" class="fixed inset-0 z-[100] flex items-center justify-center hidden bg-slate-900/50 backdrop-blur-sm transition-opacity opacity-0 duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center px-6 py-4 border-b border-slate-100 bg-amber-50">
            <h3 class="text-lg font-bold text-amber-800">Revisi Pemasangan</h3>
            <button type="button" onclick="closeRevisiModal()" class="text-slate-400 hover:text-slate-600 transition-colors focus:outline-none rounded-md hover:bg-slate-100 p-1 cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form id="revisiForm" method="POST" action="{{ route('customers.verification.revisi', $customer->id) }}">
            @csrf
            <div class="p-6">
                <p class="text-sm text-slate-600 mb-4">Pelanggan akan dikembalikan ke antrean pemasangan. Silakan tulis catatan perbaikan untuk teknisi:</p>
                <div class="mb-4">
                    <label for="reason" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Catatan Revisi <span class="text-red-500">*</span></label>
                    <textarea name="reason" id="reason" rows="3" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20" required placeholder="Contoh: Kabel perlu dirapikan, redaman terlalu tinggi, dll."></textarea>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
                <button type="button" onclick="closeRevisiModal()" class="px-5 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-md hover:bg-slate-50 transition-colors cursor-pointer">Batal</button>
                <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-amber-600 rounded-md hover:bg-amber-700 transition-colors shadow-sm cursor-pointer">Kirim ke Teknisi</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL TOLAK (FINAL, TERMINAL — PELANGGAN HARUS DAFTAR ULANG) --}}
<div id="rejectModal" class="fixed inset-0 z-[100] flex items-center justify-center hidden bg-slate-900/50 backdrop-blur-sm transition-opacity opacity-0 duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center px-6 py-4 border-b border-slate-100 bg-red-50">
            <h3 class="text-lg font-bold text-red-800">Tolak Pelanggan</h3>
            <button type="button" onclick="closeRejectModal()" class="text-slate-400 hover:text-slate-600 transition-colors focus:outline-none rounded-md hover:bg-slate-100 p-1 cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        <form id="rejectForm" method="POST" action="{{ route('customers.verification.reject', $customer->id) }}">
            @csrf
            <div class="p-6">
                <p class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mb-4">
                    Ini keputusan <span class="font-bold">final</span>. Pelanggan tidak bisa dibuka lagi — masuk daftar Pelanggan Gagal, dan kalau mau lanjut harus registrasi ulang dari awal.
                </p>
                <div class="mb-4">
                    <label for="reject_reason" class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Alasan Penolakan <span class="text-red-500">*</span></label>
                    <textarea name="reason" id="reject_reason" rows="3" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-lg focus:outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20" required placeholder="Contoh: Pelanggan tidak memenuhi kriteria / belum melunasi pembayaran."></textarea>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
                <button type="button" onclick="closeRejectModal()" class="px-5 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-md hover:bg-slate-50 transition-colors cursor-pointer">Batal</button>
                <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition-colors shadow-sm cursor-pointer">Tolak Final</button>
            </div>
        </form>
    </div>
</div>

@endsection

@section('scripts')
<script>
    // ── TAB SWITCHING ──────────────────────────────────────────────────
    function switchTab(tab) {
        document.querySelectorAll('.tab-panel').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('border-sky-600', 'text-sky-700', 'bg-sky-50/60');
            btn.classList.add('border-transparent', 'text-slate-500');
        });

        document.getElementById('tab-' + tab).classList.remove('hidden');

        const activeBtn = document.getElementById('tab-btn-' + tab);
        activeBtn.classList.remove('border-transparent', 'text-slate-500');
        activeBtn.classList.add('border-sky-600', 'text-sky-700', 'bg-sky-50/60');
    }

    // ── LIGHTBOX ───────────────────────────────────────────────────────
    function openPhotoLightbox(src, caption) {
        const lb = document.getElementById('photo-lightbox');
        document.getElementById('lightbox-img').src = src;
        document.getElementById('lightbox-caption').textContent = caption || '';
        lb.classList.remove('hidden');
        lb.classList.add('flex');
    }

    function closePhotoLightbox() {
        const lb = document.getElementById('photo-lightbox');
        lb.classList.add('hidden');
        lb.classList.remove('flex');
    }

    // Escape key for modals
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePhotoLightbox();
            if (typeof closeRevisiModal === 'function') closeRevisiModal();
            if (typeof closeRejectModal === 'function') closeRejectModal();
        }
    });

    // ── MODAL REVISI ───────────────────────────────────────────────────
    function openRevisiModal() {
        const modal = document.getElementById('revisiModal');
        modal.classList.remove('hidden');
        // Trigger reflow
        void modal.offsetWidth;
        modal.classList.remove('opacity-0');
        modal.querySelector('div').classList.remove('scale-95');
        modal.querySelector('textarea').focus();
    }

    function closeRevisiModal() {
        const modal = document.getElementById('revisiModal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // ── MODAL TOLAK ────────────────────────────────────────────────────
    function openRejectModal() {
        const modal = document.getElementById('rejectModal');
        modal.classList.remove('hidden');
        void modal.offsetWidth;
        modal.classList.remove('opacity-0');
        modal.querySelector('div').classList.remove('scale-95');
        modal.querySelector('textarea').focus();
    }

    function closeRejectModal() {
        const modal = document.getElementById('rejectModal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }

    // ── CALCULATION LOGIC ──────────────────────────────────────────────
    // PREVIEW SAJA. Angka final dihitung ulang server di
    // App\Services\InitialInvoiceService — rumus di sini wajib dijaga identik
    // supaya yang dilihat admin sama dengan yang tersimpan, tapi kalau menyimpang
    // yang menang tetap server.
    function calculateFees() {
        // Parse inputs
        const baseMonthly = parseFloat(document.getElementById('base_monthly_bill').value) || 0;
        const discount = parseFloat(document.getElementById('fv_discount').value) || 0;
        const ppnRate = parseFloat(document.getElementById('fv_ppn').value) || 0;

        const instFee = parseFloat(document.getElementById('fv_extra_installation_fee').value) || 0;
        const cableFee = parseFloat(document.getElementById('fv_extra_cable_fee').value) || 0;
        const poleFee = parseFloat(document.getElementById('fv_extra_pole_fee').value) || 0;

        // Calculate Prorate
        const issueDateInput = document.getElementById('issue_date').value;
        let prorateAmount = 0;
        let daysActive = 0;
        let daysInMonth = 30;

        if (issueDateInput) {
            const issueDateObj = new Date(issueDateInput);
            const year = issueDateObj.getFullYear();
            const month = issueDateObj.getMonth();
            const date = issueDateObj.getDate();
            
            daysInMonth = new Date(year, month + 1, 0).getDate();
            daysActive = daysInMonth - date + 1; // Termasuk hari ini

            // Prorate formula = (daysActive / daysInMonth) * baseMonthly
            prorateAmount = Math.round((daysActive / daysInMonth) * baseMonthly);
            
            document.getElementById('prorate_info').textContent = `${daysActive} dari ${daysInMonth} hari bulan ini`;
        } else {
            prorateAmount = baseMonthly;
        }

        // Apply
        document.getElementById('fv_prorate_amount').value = prorateAmount;

        // Subtotal = prorata + biaya sekali bayar; PPN dihitung dari subtotal
        // setelah diskon (persen, sama seperti render di invoices/show.blade.php).
        const subtotal = prorateAmount + instFee + cableFee + poleFee;
        const afterDiscount = Math.max(0, subtotal - discount);
        const ppnAmount = Math.round(afterDiscount * (ppnRate / 100) * 100) / 100;

        document.getElementById('fv_subtotal').value = subtotal;
        document.getElementById('fv_total_amount').value = afterDiscount + ppnAmount;
    }

    // ── CONFIRM ACTIVATION ─────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        // Initial calculation
        calculateFees();

        const verifyForm = document.getElementById('verifyForm');
        if (verifyForm) {
            verifyForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const total = document.getElementById('fv_total_amount').value;
                const totalFormatted = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(total);

                const message = `
                    <div class="space-y-3">
                        <p>Tindakan ini akan:</p>
                        <ul class="list-disc list-inside text-slate-700 space-y-1 ml-2">
                            <li>Mengaktifkan pelanggan (status &rarr; <span class="font-bold text-emerald-600">Aktif</span>)</li>
                            <li>Menerbitkan tagihan pertama sebesar <span class="font-bold font-mono text-slate-900">${totalFormatted}</span></li>
                        </ul>
                        <p class="font-medium text-slate-800 mt-2">Apakah Anda yakin?</p>
                    </div>
                `;

                window.Dialog.show({
                    title: 'Konfirmasi Aktivasi Pelanggan',
                    contentHtml: message,
                    icon: 'warning',
                    buttons: [
                        { text: 'Batal', type: 'secondary' },
                        { text: 'Lanjutkan Aktivasi', type: 'primary', onClick: () => {
                            window.Dialog.close();
                            verifyForm.submit();
                        }}
                    ]
                });
            });
        }

        // Auto-switch to verifikasi tab if there are errors
        @if($errors->any())
        switchTab('verifikasi');
        @endif
    });
</script>
@endsection
