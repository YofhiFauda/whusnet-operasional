@extends('layouts.app')

@section('title', 'Detail Pelanggan - Whusnet Operasional')
@section('page_title', 'Detail Pelanggan')

@section('content')
<!-- Breadcrumbs & Actions Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <nav class="flex text-xs font-semibold text-slate-400 uppercase tracking-wider gap-2 mb-2">
            <a href="/customers" class="hover:text-slate-700 transition-colors">Daftar Pelanggan</a>
            <span>/</span>
            <span class="text-slate-600">Detail Pelanggan</span>
        </nav>
        <h1 class="text-xl font-bold text-slate-800 tracking-tight">Detail Pelanggan: {{ $customer->full_name }}</h1>
    </div>
    <div class="flex gap-2">
        @can('customers.detail.installation.activate')
            @php
                $hasWorkflowTask = $customerTasks->whereIn('task_type', [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value])->isNotEmpty();
                $isProvenLegacyActive = $customer->old_customer_id && $customer->customerService?->request_status === 'ACTIVE';
            @endphp
            @if($customer->data_completeness_status !== 'siap_billing' && $customer->status !== 'active' && !$hasWorkflowTask && $isProvenLegacyActive)
                <form action="{{ route('customers.activate', $customer->id) }}" method="POST" class="inline" onsubmit="event.preventDefault(); window.confirmAction('Pelanggan ini belum aktif lewat proses verifikasi normal. Aktifkan manual sekarang? CID akan dibuat dan tagihan pertama akan diterbitkan.', this);">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none cursor-pointer"
                            title="Khusus pelanggan yang belum otomatis aktif dari alur verifikasi (mis. data migrasi lama). Untuk pelanggan baru, aktivasi sudah otomatis saat verifikasi admin selesai."
                            @if(!$completeness['is_ready_billing']) disabled title="Data profil belum lengkap untuk diaktifkan" @endif>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Aktivasi Manual
                    </button>
                </form>
            @endif
        @endcan
        <a href="/customers/{{ $customer->id }}/edit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit Profil
        </a>
        <a href="/customers" class="inline-flex items-center gap-1.5 px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-md transition-colors text-xs font-semibold shadow-sm focus:outline-none">
            Kembali
        </a>
    </div>
</div>

@php
    $completeness = $customer->dataCompleteness();
    // missing_required and missing_optional are [key => label] maps returned by CustomerValidationService
    $missingRequiredLabels = array_values($completeness['missing_required']);
    $missingOptionalLabels = array_values($completeness['missing_optional']);
@endphp

<!-- Data Completeness Alert / Progress Bar -->
<div class="bg-white border border-slate-200 rounded-lg p-5 mb-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="flex-1">
        <div class="flex items-center gap-2">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Kelengkapan Data Profil Pelanggan</h3>
            @if(count($completeness['missing_required']) > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-red-50 text-red-700 border border-red-100 uppercase tracking-wider">Kurang Data Wajib</span>
            @elseif(count($completeness['missing_optional']) > 0)
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-amber-50 text-amber-700 border border-amber-100 uppercase tracking-wider">Kurang Data Opsional</span>
            @else
                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold bg-green-50 text-green-700 border border-green-100 uppercase tracking-wider">100% Lengkap</span>
            @endif
        </div>
        <p class="text-xs text-slate-500 mt-1 leading-relaxed">
            @if(count($completeness['missing_required']) > 0)
                <strong class="text-red-600">Kekurangan Data Wajib:</strong> {{ implode(', ', $missingRequiredLabels) }}. Mohon segera lakukan pengeditan profil.
            @elseif(count($completeness['missing_optional']) > 0)
                <strong class="text-amber-600">Kekurangan Data Opsional:</strong> {{ implode(', ', $missingOptionalLabels) }}.
            @else
                Semua data administrasi dan teknis telah terisi lengkap.
            @endif
        </p>
    </div>
    <div class="w-full md:w-64 shrink-0">
        <div class="flex items-center justify-between mb-1.5 text-xs font-semibold">
            <span class="text-slate-500">Persentase Terisi</span>
            <span class="text-sky-600 data-text">{{ $completeness['percentage'] }}%</span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden border border-slate-200/50">
            <div class="h-full rounded-full transition-all duration-300 {{ count($completeness['missing_required']) > 0 ? 'bg-red-500' : (count($completeness['missing_optional']) > 0 ? 'bg-amber-500' : 'bg-green-500') }}" style="width: {{ $completeness['percentage'] }}%"></div>
        </div>
    </div>
</div>

<!-- Main Grid -->
<div class="grid grid-cols-1 lg:grid-cols-4 gap-8 items-start">
    
    <!-- LEFT COLUMN: Profile Card & Timeline Progress -->
    <div class="lg:col-span-1 flex flex-col gap-6">
        <!-- Profile summary card -->
        <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm">
            <div class="flex flex-col items-center text-center">
                <!-- Avatar / Initials -->
                <div class="h-16 w-16 bg-sky-50 border border-sky-100 rounded-full flex items-center justify-center font-bold text-xl text-sky-600 mb-4 shadow-inner">
                    {{ strtoupper(substr($customer->full_name, 0, 2)) }}
                </div>
                <h3 class="font-bold text-slate-800 text-base leading-tight">{{ $customer->full_name }}</h3>
                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mt-1">{{ $displayIdLabel ?? 'ID' }}</span>
                <span class="text-xs font-mono text-slate-600 mt-0.5 data-text">{{ $displayId }}</span>
                
                <!-- Status Badge -->
                <div class="mt-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold border {{ $customer->subscriptionStatus?->badgeClasses() ?? 'bg-slate-50 text-slate-700 border-slate-100' }}">
                        {{ $customer->subscriptionStatus->name ?? Str::headline($customer->status) }}
                    </span>
                </div>
            </div>

            <!-- Contacts list -->
            <div class="mt-6 space-y-3 pt-6 border-t border-slate-100 text-xs text-slate-600">
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    <span class="font-mono data-text">{{ $customer->phone }}</span>
                </div>
                <div class="flex items-center gap-3">
                    <svg class="h-4 w-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <span class="truncate">{{ $customer->email }}</span>
                </div>
            </div>

        </div>

        <!-- Vertical Workflow Timeline Card -->
        <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm">
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">TIMELINE PROSES</h4>
            
            <div class="flow-root">
                <ul class="-mb-8">
                    @foreach($timeline as $index => $step)
                    <li>
                        <div class="relative pb-8">
                            <!-- Line connecting dots -->
                            @if(!$loop->last)
                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-slate-200" aria-hidden="true"></span>
                            @endif
                            
                            <div class="relative flex space-x-3">
                                <div>
                                    <!-- Indicator Dot -->
                                    @if($step['status'] === 'completed')
                                        <span class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center ring-4 ring-white text-white">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </span>
                                    @elseif($step['status'] === 'current')
                                        <span class="h-8 w-8 rounded-full bg-sky-500 flex items-center justify-center ring-4 ring-white text-white animate-pulse">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </span>
                                    @elseif($step['status'] === 'warning')
                                        <span class="h-8 w-8 rounded-full bg-amber-500 flex items-center justify-center ring-4 ring-white text-white">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </span>
                                    @elseif($step['status'] === 'danger')
                                        <span class="h-8 w-8 rounded-full bg-red-50 flex items-center justify-center ring-4 ring-white text-red-600 border border-red-200">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </span>
                                    @else
                                        <span class="h-8 w-8 rounded-full bg-slate-100 flex items-center justify-center ring-4 ring-white text-slate-400 border border-slate-200">
                                            <span class="text-xs font-bold font-mono">{{ $loop->iteration }}</span>
                                        </span>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0 pt-1.5">
                                    <p class="text-xs font-semibold text-slate-800">{{ $step['title'] }}</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5 font-mono data-text">{{ $step['date'] }}</p>
                                    <p class="text-[10px] text-slate-500 mt-1 leading-relaxed">{{ $step['notes'] }}</p>
                                </div>
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: 10-Tab Area -->
    <div class="lg:col-span-3 flex flex-col">
        <!-- Tabs Buttons Nav -->
        <div class="border-b border-slate-200 bg-white rounded-t-lg overflow-x-auto flex shadow-sm scrollbar-none">
            <button onclick="switchTab('ringkasan')" id="tab-btn-ringkasan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-sky-600 text-sky-600 focus:outline-none cursor-pointer whitespace-nowrap">Ringkasan</button>
            <button onclick="switchTab('identitas')" id="tab-btn-identitas" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Identitas</button>
            <button onclick="switchTab('alamat')" id="tab-btn-alamat" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Alamat</button>
            <button onclick="switchTab('pop')" id="tab-btn-pop" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">POP/Cabang</button>
            <button onclick="switchTab('survey')" id="tab-btn-survey" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Survey</button>
            <button onclick="switchTab('pemasangan')" id="tab-btn-pemasangan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Pemasangan</button>
            <button onclick="switchTab('perangkat')" id="tab-btn-perangkat" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Perangkat</button>
            <button onclick="switchTab('paket-layanan')" id="tab-btn-paket-layanan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Paket & Layanan</button>
            <button onclick="switchTab('billing')" id="tab-btn-billing" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Billing</button>
            <button onclick="switchTab('tagihan')" id="tab-btn-tagihan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Tagihan</button>
            <button onclick="switchTab('pembayaran')" id="tab-btn-pembayaran" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Pembayaran</button>
            <button onclick="switchTab('dokumen')" id="tab-btn-dokumen" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Dokumen</button>
            <button onclick="switchTab('riwayat-ticketing')" id="tab-btn-riwayat-ticketing" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Riwayat Ticketing</button>
            <button onclick="switchTab('riwayat-perubahan')" id="tab-btn-riwayat-perubahan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Riwayat Perubahan</button>
            @if($customer->customerTechnicalDetail)
            <button onclick="switchTab('teknis-lama')" id="tab-btn-teknis-lama" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Detail Teknis Lama</button>
            @endif
        </div>

        <!-- Tabs Content Body -->
        <div class="bg-white border-x border-b border-slate-200 rounded-b-lg p-6 min-h-[450px] shadow-sm">
            
            <!-- Tab 1: Ringkasan -->
            <div id="tab-content-ringkasan" class="tab-content space-y-6">
                <!-- Completeness Overview -->
                <div class="border border-slate-100 rounded-lg p-5 bg-gradient-to-r from-slate-50/50 to-white">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">STATUS KELENGKAPAN DATA</span>
                    <div class="flex items-center gap-3">
                        @php
                            $completenessColor = match($customer->data_completeness_status) {
                                'siap_billing' => 'bg-green-100 text-green-800 border-green-200',
                                'lengkap' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                'perlu_dilengkapi' => 'bg-amber-100 text-amber-800 border-amber-200',
                                'draft' => 'bg-slate-100 text-slate-800 border-slate-200',
                                default => 'bg-slate-100 text-slate-800 border-slate-200',
                            };
                            $completenessLabel = match($customer->data_completeness_status) {
                                'siap_billing' => 'Siap Billing',
                                'lengkap' => 'Lengkap',
                                'perlu_dilengkapi' => 'Perlu Dilengkapi',
                                'draft' => 'Draft',
                                default => ucwords(str_replace('_', ' ', $customer->data_completeness_status)),
                            };
                        @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border {{ $completenessColor }}">
                            {{ $completenessLabel }}
                        </span>
                        <span class="text-xs text-slate-500">
                            Profil data terisi <strong class="text-sky-600">{{ $completeness['percentage'] }}%</strong> dari total 28 parameter evaluasi.
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Active Subscription card -->
                    <div class="border border-slate-100 rounded-lg p-4 bg-slate-50/30">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">LANGGANAN AKTIF</span>
                        @if($customer->internetPackage)
                            <h4 class="text-sm font-semibold text-slate-800">{{ $customer->internetPackage->package_code }} - {{ $customer->internetPackage->name }}</h4>
                            <p class="text-xs text-slate-500 mt-1">Kategori: {{ $customer->internetPackage->category }}</p>
                            <div class="flex items-baseline mt-3">
                                <span class="text-lg font-bold text-slate-800 data-text">Rp {{ number_format($customer->customerService?->total_monthly_bill ?? (($customer->internetPackage->monthly_price - ($customer->discount_amount ?? 0)) * (1 + ($customer->tax_percent ?? 0) / 100)), 0, ',', '.') }}</span>
                                <span class="text-[10px] text-slate-400 ml-1">/bulan (Nett)</span>
                            </div>
                        @else
                            <p class="text-slate-400 text-xs py-2">Belum memilih paket layanan</p>
                        @endif
                    </div>
                    
                    <!-- Address Card -->
                    <div class="border border-slate-100 rounded-lg p-4 bg-slate-50/30">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">ALAMAT INSTALASI</span>
                        <p class="text-xs text-slate-700 font-semibold leading-relaxed">{{ $customer->address ?? 'Belum diisi' }}</p>
                        <p class="text-[10px] text-slate-500 mt-1.5">
                            Kel. {{ $customer->village->name ?? ($customer->customerAddress->village ?? '-') }}, 
                            Kec. {{ $customer->district->name ?? ($customer->customerAddress->district ?? '-') }}, 
                            {{ $customer->city->name ?? ($customer->customerAddress->city ?? '-') }}
                        </p>
                        <div class="mt-2.5 flex items-center gap-1.5 font-mono text-[9px] text-slate-400 data-text">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                            <span>Lat/Long: {{ $customer->latitude ?? ($customer->customerAddress->latitude ?? '-') }}, {{ $customer->longitude ?? ($customer->customerAddress->longitude ?? '-') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Technical summary card -->
                <div class="border border-slate-100 rounded-lg p-5">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3">RINGKASAN TEKNIS JARINGAN</span>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div>
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">Nomer CID / REQ ID</span>
                            @if(auth()->user()->hasPermission('customers.detail.installation.validate'))
                            <button type="button" x-data @click="$dispatch('open-modal', 'network-assignment')"
                                    class="font-mono font-medium text-primary hover:underline data-text cursor-pointer text-left"
                                    title="Klik untuk atur Mini POP & Distribusi">
                                {{ $displayId ?? $customer->cid ?? 'Belum dialokasikan' }}
                            </button>
                            @else
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $displayId ?? $customer->cid ?? 'Belum dialokasikan' }}</span>
                            @endif
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">POP Cabang</span>
                            <span class="font-medium text-slate-800">{{ $customer->pop->name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">Mini POP (OLT)</span>
                            <span class="font-medium text-slate-800">{{ $customer->miniPop->name ?? 'Belum di-assign' }}</span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">Distribusi</span>
                            <span class="font-medium text-slate-800">{{ $customer->distribution->code ?? 'Belum di-assign' }}</span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">IP Address</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $customer->ip_address ?? 'Belum dialokasikan' }}</span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">ONT Serial Number</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $customer->ont_sn ?? 'Belum terpasang' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Missing Fields alert -->
                @if(count($completeness['missing_required']) > 0 || count($completeness['missing_optional']) > 0)
                <div class="border border-slate-200 rounded-lg p-5 bg-amber-50/20">
                    <span class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">PARAMETER YANG BELUM LENGKAP</span>
                    <div class="space-y-3 text-xs">
                        @if(count($completeness['missing_required']) > 0)
                        <div>
                            <span class="font-semibold text-red-600 block mb-1">Field Wajib (Mencegah Layanan Aktif/Billing):</span>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($missingRequiredLabels as $label)
                                    <span class="px-2.5 py-0.5 bg-red-50 text-red-700 border border-red-100 rounded text-[10px] font-mono">{{ $label }}</span>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if(count($completeness['missing_optional']) > 0)
                        <div>
                            <span class="font-semibold text-amber-600 block mb-1">Field Opsional/Teknis:</span>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($missingOptionalLabels as $label)
                                    <span class="px-2.5 py-0.5 bg-amber-50 text-amber-700 border border-amber-100 rounded text-[10px] font-mono">{{ $label }}</span>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            <!-- Tab 2: Identitas -->
            <div id="tab-content-identitas" class="tab-content hidden space-y-6">
                <div class="border border-slate-100 rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-100">
                        <span class="text-xs font-semibold text-slate-600">Formulir Identitas Pelanggan</span>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Nama Lengkap</span>
                            <span class="font-semibold text-slate-800">{{ $customer->full_name }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Nomor Identitas (NIK)</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $customer->identity_number ?? 'Belum diisi' }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Jenis Kelamin</span>
                            <span class="font-semibold text-slate-800">{{ $customer->gender?->label() ?? 'Belum diisi' }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Nomor HP Utama</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $customer->primary_phone ?? $customer->phone ?? 'Belum diisi' }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Nomor HP Alternatif</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $customer->alternative_phone ?? 'Belum diisi' }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Alamat Email</span>
                            <span class="font-semibold text-slate-800">{{ $customer->email ?? 'Belum diisi' }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between md:col-span-2">
                            <span class="text-slate-400">Tanggal Registrasi</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ \App\Support\IndonesianDate::date($customer->registration_date) }}</span>
                        </div>
                    </div>
                </div>

                <div class="border border-slate-100 rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-100">
                        <span class="text-xs font-semibold text-slate-600">Informasi Referral & Sales</span>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
                        <div class="border border-slate-100 rounded-lg p-4 bg-slate-50/10">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">ID SALES</span>
                            <span class="font-mono font-medium text-slate-800 mt-1 block data-text">{{ $customer->sales_code ?? '-' }}</span>
                        </div>
                        <div class="border border-slate-100 rounded-lg p-4 bg-slate-50/10">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">KODE AGENT</span>
                            <span class="font-mono font-medium text-slate-800 mt-1 block data-text">{{ $customer->agent_code ?? '-' }}</span>
                        </div>
                        <div class="border border-slate-100 rounded-lg p-4 bg-slate-50/10">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">REFERRAL PELANGGAN</span>
                            <span class="font-mono font-medium text-slate-800 mt-1 block data-text">{{ $customer->referral_customer_code ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Alamat -->
            <div id="tab-content-alamat" class="tab-content hidden space-y-6">
                <div class="border border-slate-100 rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-100">
                        <span class="text-xs font-semibold text-slate-600">Alamat Instalasi Detail</span>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                        <div class="py-1.5 border-b border-slate-50 flex justify-between md:col-span-2">
                            <span class="text-slate-400">Alamat Lengkap</span>
                            <span class="font-semibold text-slate-800 text-right">{{ $customer->address ?? 'Belum diisi' }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Desa / Kelurahan</span>
                            <span class="font-semibold text-slate-800">{{ $customer->village->name ?? ($customer->customerAddress->village ?? 'Belum diisi') }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Kecamatan</span>
                            <span class="font-semibold text-slate-800">{{ $customer->district->name ?? ($customer->customerAddress->district ?? 'Belum diisi') }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Kota / Kabupaten</span>
                            <span class="font-semibold text-slate-800">{{ $customer->city->name ?? ($customer->customerAddress->city ?? 'Belum diisi') }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Provinsi</span>
                            <span class="font-semibold text-slate-800">{{ $customer->customerAddress->province ?? 'Jawa Timur' }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Garis Lintang (Latitude)</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $customer->latitude ?? ($customer->customerAddress->latitude ?? 'Belum diisi') }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Garis Bujur (Longitude)</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $customer->longitude ?? ($customer->customerAddress->longitude ?? 'Belum diisi') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 4: POP/Cabang -->
            <div id="tab-content-pop" class="tab-content hidden space-y-6">
                <div class="border border-slate-100 rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-100">
                        <span class="text-xs font-semibold text-slate-600">Data POP / Cabang Terkoneksi</span>
                    </div>
                    @if($customer->pop)
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Nama POP / Cabang</span>
                            <span class="font-semibold text-slate-800">{{ $customer->pop->name }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Kode Cabang</span>
                            <span class="font-mono font-semibold text-slate-800 data-text">{{ $customer->pop->code }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Tipe Cabang</span>
                            <span class="font-semibold text-slate-800 uppercase text-[10px] tracking-wider">{{ $customer->pop->type }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Status POP</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-green-50 text-green-700 border border-green-100 uppercase">
                                {{ $customer->pop->status ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between md:col-span-2">
                            <span class="text-slate-400">Alamat Kantor POP</span>
                            <span class="font-semibold text-slate-800 text-right">
                                {{ $customer->pop->address }}, Kel. {{ $customer->pop->village }}, Kec. {{ $customer->pop->district }}, {{ $customer->pop->city }}
                            </span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Penanggung Jawab (PIC)</span>
                            <span class="font-semibold text-slate-800">{{ $customer->pop->pic_name ?? 'Belum ditentukan' }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Nomor HP PIC</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $customer->pop->pic_phone ?? 'Belum ditentukan' }}</span>
                        </div>
                    </div>
                    @else
                    <div class="p-8 text-center text-slate-400 text-xs">
                        <svg class="mx-auto h-8 w-8 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        Belum ada POP/Cabang yang di-assign untuk pelanggan ini.
                    </div>
                    @endif
                </div>
            </div>

            <!-- Tab: Survey -->
            <div id="tab-content-survey" class="tab-content hidden space-y-6">
                @include('customers.tabs._survey')
            </div>

            <!-- Tab: Pemasangan -->
            <div id="tab-content-pemasangan" class="tab-content hidden space-y-6">
                @include('customers.tabs._installation')
            </div>

            <div id="tab-content-perangkat" class="tab-content hidden space-y-6">
                @include('customers.tabs._device')
            </div>

            <!-- Tab 5: Paket & Layanan -->
            <div id="tab-content-paket-layanan" class="tab-content hidden space-y-6">
                <div class="border border-slate-100 rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-100">
                        <span class="text-xs font-semibold text-slate-600">Paket Internet & Status Layanan Jaringan</span>
                    </div>
                    <div class="p-5 text-xs space-y-5">
                        @if($customer->internetPackage)
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="border border-slate-100 rounded-lg p-4 bg-slate-50/10">
                                <span class="block text-[9px] font-bold text-slate-400 uppercase">KODE LAYANAN</span>
                                <span class="font-mono font-semibold text-slate-800 mt-1 block data-text">{{ $customer->internetPackage->package_code }} - {{ $customer->internetPackage->name }}</span>
                            </div>
                            <div class="border border-slate-100 rounded-lg p-4 bg-slate-50/10">
                                <span class="block text-[9px] font-bold text-slate-400 uppercase">KATEGORI LAYANAN</span>
                                <span class="font-semibold text-slate-800 mt-1 block">{{ $customer->internetPackage->category }}</span>
                            </div>
                            <div class="border border-slate-100 rounded-lg p-4 bg-slate-50/10">
                                <span class="block text-[9px] font-bold text-slate-400 uppercase">KECEPATAN (BANDWIDTH)</span>
                                <span class="font-mono font-semibold text-sky-600 mt-1 block data-text">
                                    {{ $customer->internetPackage->download_speed_mbps }} Mbps Down / {{ $customer->internetPackage->upload_speed_mbps }} Mbps Up
                                </span>
                            </div>
                        </div>
                        @else
                        <div class="p-4 text-center text-slate-400">
                            Belum ada paket internet yang terpilih.
                        </div>
                        @endif

                        <div class="border border-slate-100 rounded-lg p-4 bg-slate-50/30">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-2">INTEGRASI TEKNIS</span>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 font-mono text-[11px] data-text">
                                <div class="flex justify-between py-1 border-b border-slate-100/50">
                                    <span class="text-slate-400">ONT Serial Number</span>
                                    <span class="text-slate-800">{{ $customer->ont_sn ?? 'Belum terpasang' }}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-slate-100/50">
                                    <span class="text-slate-400">IP Address Dialed</span>
                                    <span class="text-slate-800">{{ $customer->ip_address ?? 'Belum teralokasi' }}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-slate-100/50">
                                    <span class="text-slate-400">Nama Perangkat OLT</span>
                                    <span class="text-slate-800">{{ $customer->olt_code ?? 'Belum diisi' }}</span>
                                </div>
                                {{-- olt_number diisi oleh teknisi dan digunakan untuk generate CID --}}
                                <div class="flex justify-between py-1 border-b border-slate-100/50">
                                    <span class="text-slate-400">
                                        Nomor OLT
                                        <span class="text-[9px] text-sky-500 font-bold ml-1" title="Nilai ini digunakan saat generate CID pelanggan">[CID]</span>
                                    </span>
                                    @php $oltNumber = $customer->customerTechnicalDetail?->olt_number; @endphp
                                    @if($oltNumber)
                                        <span class="text-sky-700 font-bold">{{ $oltNumber }}</span>
                                    @else
                                        <span class="text-amber-600 italic">Belum diisi teknisi</span>
                                    @endif
                                </div>
                                <div class="flex justify-between py-1 border-b border-slate-100/50">
                                    <span class="text-slate-400">Kode Kotak ODP</span>
                                    <span class="text-slate-800">{{ $customer->odp_code ?? 'Belum terhubung' }}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-slate-100/50">
                                    <span class="text-slate-400">VLAN ID Jaringan</span>
                                    <span class="text-slate-800">{{ $customer->vlan_id ?? 'Belum ditentukan' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 6: Billing -->
            <div id="tab-content-billing" class="tab-content hidden space-y-6">
                <div class="border border-slate-100 rounded-lg overflow-hidden">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-100">
                        <span class="text-xs font-semibold text-slate-600">Rincian Biaya Bulanan & Billing Cycle</span>
                    </div>
                    <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-8 text-xs">
                        @php
                            $monthlyPrice  = (float)($customer->customerService?->monthly_price ?? ($customer->internetPackage?->monthly_price ?? 0));
                            $discount      = (float)($customer->customerService?->discount ?? ($customer->discount_amount ?? 0));
                            $otherFee      = (float)($customer->customerService?->other_fee ?? 0);
                            // Gunakan nilai ppn dari customer_services secara langsung (bisa 0 atau nilai tertentu)
                            // Jangan fallback ke 11 karena 0 berarti memang tanpa PPN
                            $ppnPercent    = $customer->customerService ? (float)$customer->customerService->ppn : (float)($customer->tax_percent ?? 0);

                            $discountedPrice = max(0, $monthlyPrice - $discount);
                            $ppnAmount       = round($discountedPrice * ($ppnPercent / 100), 2);
                            // Gunakan total dari service snapshot jika tersedia, hitung ulang hanya sebagai fallback
                            $totalBill = $customer->customerService
                                ? (float)$customer->customerService->total_monthly_bill
                                : ($discountedPrice + $ppnAmount + $otherFee);
                        @endphp
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">BREAKDOWN BIAYA NETT</span>
                            <div class="space-y-3 font-mono text-[11px] data-text">
                                <div class="flex justify-between text-slate-500">
                                    <span>Harga Paket Internet</span>
                                    <span>Rp {{ number_format($monthlyPrice, 2, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between text-green-600">
                                    <span>Potongan Diskon</span>
                                    <span>- Rp {{ number_format($discount, 2, ',', '.') }}</span>
                                </div>
                                <div class="flex justify-between text-slate-500">
                                    <span>PPN ({{ number_format($ppnPercent, 0) }}%)</span>
                                    <span>Rp {{ number_format($ppnAmount, 2, ',', '.') }}</span>
                                </div>
                                @if($otherFee > 0)
                                <div class="flex justify-between text-slate-500">
                                    <span>Biaya Lain di luar Standar</span>
                                    <span>Rp {{ number_format($otherFee, 2, ',', '.') }}</span>
                                </div>
                                @endif
                                <hr class="border-dashed border-slate-200">
                                <div class="flex justify-between text-xs font-bold text-slate-900">
                                    <span>Total Biaya Per Bulan</span>
                                    <span>Rp {{ number_format($totalBill, 2, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">MASA AKTIF & PERIODE</span>
                            <div class="space-y-3 text-xs">
                                <div class="flex justify-between py-1.5 border-b border-slate-50">
                                    <span class="text-slate-400">Tanggal Aktivasi Layanan</span>
                                    <span class="font-mono font-semibold text-slate-800 data-text">
                                        {{ $customer->customerService?->activation_date ? \App\Support\IndonesianDate::date($customer->customerService->activation_date) : 'Belum diaktivasi' }}
                                    </span>
                                </div>
                                @if($customer->customerService?->activation_time)
                                <div class="flex justify-between py-1.5 border-b border-slate-50">
                                    <span class="text-slate-400">Waktu Aktivasi</span>
                                    <span class="font-mono font-semibold text-slate-800 data-text">
                                        {{ substr($customer->customerService->activation_time, 0, 5) }} WIB
                                    </span>
                                </div>
                                @endif
                                @if($customer->customerService?->activated_by_name || $customer->customerService?->activatedBy)
                                <div class="flex justify-between py-1.5 border-b border-slate-50">
                                    <span class="text-slate-400">Petugas Aktivasi</span>
                                    <span class="font-semibold text-slate-800">
                                        {{ $customer->customerService->activatedBy->name ?? $customer->customerService->activated_by_name ?? '-' }}
                                    </span>
                                </div>
                                @endif
                                <div class="flex justify-between py-1.5 border-b border-slate-50">
                                    <span class="text-slate-400">Tanggal Jatuh Tempo Bulanan</span>
                                    <span class="font-mono font-semibold text-slate-800 data-text">
                                        {{ $customer->customerService?->due_date ? \App\Support\IndonesianDate::date($customer->customerService->due_date) : 'Belum ditentukan' }}
                                    </span>
                                </div>
                                <div class="flex justify-between py-1.5 border-b border-slate-50">
                                    <span class="text-slate-400">Siklus Billing</span>
                                    <span class="font-semibold text-slate-800 capitalize">
                                        {{ $customer->customerService?->billing_cycle ?? 'Monthly' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 7: Tagihan -->
            <div id="tab-content-tagihan" class="tab-content hidden space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Riwayat Tagihan Pelanggan</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Daftar invoice tagihan bulanan yang diterbitkan secara manual maupun sistem.</p>
                    </div>
                    @can('invoices.create')
                        @if((in_array($customer->status, ['active', 'suspended']) || $customer->data_completeness_status === 'siap_billing') && $customer->customerService)
                            <button onclick="openInvoiceModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded-md transition-colors text-xs font-semibold shadow-sm cursor-pointer focus:outline-none">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Buat Tagihan Manual
                            </button>
                        @else
                            <button disabled class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 border border-slate-200 text-slate-400 rounded-md text-xs font-semibold cursor-not-allowed focus:outline-none" title="Pelanggan harus berstatus Active atau Siap Billing dengan layanan aktif">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                                Buat Tagihan Manual
                            </button>
                        @endif
                    @endcan
                </div>

                @if($customer->invoices && $customer->invoices->count() > 0)
                    @php
                        $invoicesAwal = $customer->invoices->filter(fn($inv) => in_array($inv->invoice_type?->value, ['awal', 'reaktivasi'], true));
                        $invoicesBulanan = $customer->invoices->filter(fn($inv) => $inv->invoice_type?->value === 'bulanan');
                    @endphp

                    @foreach ([
                        ['title' => 'Tagihan Awal / Registrasi', 'rows' => $invoicesAwal, 'badge' => 'bg-orange-50 text-orange-700 border-orange-200'],
                        ['title' => 'Tagihan Bulanan', 'rows' => $invoicesBulanan, 'badge' => 'bg-sky-50 text-sky-700 border-sky-200'],
                    ] as $group)
                        <div class="{{ !$loop->first ? 'mt-6' : '' }}">
                            <h4 class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-2">{{ $group['title'] }} ({{ $group['rows']->count() }})</h4>
                            @if($group['rows']->count() > 0)
                                <div class="overflow-x-auto border border-slate-200 rounded-lg shadow-sm">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600 uppercase tracking-wider text-[10px]">
                                                <th class="px-4 py-3">No. Tagihan</th>
                                                <th class="px-4 py-3">Jenis</th>
                                                <th class="px-4 py-3">Periode</th>
                                                <th class="px-4 py-3">Tanggal Terbit</th>
                                                <th class="px-4 py-3">Jatuh Tempo</th>
                                                <th class="px-4 py-3 text-right">Total Tagihan</th>
                                                <th class="px-4 py-3 text-center">Status</th>
                                                <th class="px-4 py-3">Dibuat Oleh</th>
                                                @can('payments.create')
                                                    <th class="px-4 py-3 text-center">Aksi</th>
                                                @endcan
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100 text-slate-700">
                                            @foreach($group['rows'] as $invoice)
                                                <tr class="hover:bg-slate-50/50 transition-colors" id="invoice-row-{{ $invoice->id }}">
                                                    <td class="px-4 py-3 font-mono font-bold text-slate-800">
                                                        @can('invoices.view')
                                                            <a href="{{ route('invoices.show', $invoice->id) }}" class="text-sky-700 hover:text-sky-900">{{ $invoice->invoice_number }}</a>
                                                        @else
                                                            {{ $invoice->invoice_number }}
                                                        @endcan
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide {{ $group['badge'] }}">
                                                            {{ $invoice->invoice_type?->label() ?? ucfirst((string) $invoice->invoice_type) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 font-mono">{{ $invoice->billing_period }}</td>
                                                    <td class="px-4 py-3">{{ \App\Support\IndonesianDate::date($invoice->issue_date) }}</td>
                                                    <td class="px-4 py-3">{{ \App\Support\IndonesianDate::date($invoice->due_date) }}</td>
                                                    <td class="px-4 py-3 text-right font-mono font-semibold">Rp {{ number_format($invoice->total_amount, 2, ',', '.') }}</td>
                                                    <td class="px-4 py-3 text-center">
                                                        @php
                                                            $statusClass = match($invoice->invoice_status->value) {
                                                                'lunas' => 'bg-green-50 text-green-700 border-green-200',
                                                                'sebagian' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                                'belum_dibayar' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                                'batal' => 'bg-red-50 text-red-700 border-red-200',
                                                                default => 'bg-slate-50 text-slate-700 border-slate-200',
                                                            };
                                                            $statusLabel = $invoice->invoice_status->label();
                                                        @endphp
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide {{ $statusClass }}" id="invoice-status-badge-{{ $invoice->id }}" data-badge-style="tag">
                                                            {{ $statusLabel }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-slate-500">{{ $invoice->creator->name ?? 'System' }}</td>
                                                    @can('payments.create')
                                                        <td class="px-4 py-3 text-center">
                                                            @if(!in_array($invoice->invoice_status->value, ['lunas', 'batal'], true))
                                                                <button type="button"
                                                                        onclick="openQuickPaymentModal({{ $invoice->id }}, '{{ $invoice->invoice_number }}', {{ (float) $invoice->remaining_amount }})"
                                                                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold uppercase tracking-wide shadow-sm cursor-pointer">
                                                                    Bayar
                                                                </button>
                                                            @else
                                                                <span class="text-slate-300 text-[10px]">—</span>
                                                            @endif
                                                        </td>
                                                    @endcan
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="py-6 text-center text-slate-400 bg-slate-50/20 border border-dashed border-slate-200 rounded-lg text-xs">
                                    Belum ada {{ strtolower($group['title']) }}.
                                </div>
                            @endif
                        </div>
                    @endforeach
                @else
                    <div class="py-12 text-center text-slate-400 bg-slate-50/20 border border-dashed border-slate-200 rounded-lg">
                        <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <h4 class="text-sm font-semibold text-slate-700">Belum ada tagihan terbit</h4>
                        <p class="text-xs text-slate-500 mt-1">Gunakan tombol "Buat Tagihan Manual" untuk membuat invoice pertama pelanggan.</p>
                    </div>
                @endif
            </div>

            <!-- Modal Pembuatan Tagihan Manual -->
            @can('invoices.create')
                @if((in_array($customer->status, ['active', 'suspended']) || $customer->data_completeness_status === 'siap_billing') && $customer->customerService)
                    @php
                        $defaultPeriod = now()->format('Y-m');
                        $defaultIssueDate = now()->format('Y-m-d');
                        $defaultDueDate = now()->addDays(14)->format('Y-m-d');
                        if ($customer->customerService->due_date) {
                            $dueDay = \Carbon\Carbon::parse($customer->customerService->due_date)->day;
                            try {
                                $defaultDueDate = now()->day($dueDay)->format('Y-m-d');
                            } catch (\Exception $e) {
                                $defaultDueDate = now()->addDays(14)->format('Y-m-d');
                            }
                        }
                    @endphp
                    <div id="manual-invoice-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity z-50 flex items-center justify-center p-4 hidden">
                        <div class="bg-white rounded-lg shadow-xl border border-slate-200 w-full max-w-md overflow-hidden transform transition-all">
                            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Buat Tagihan Manual</h3>
                                <button onclick="closeInvoiceModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <form action="{{ route('customers.invoices.manual', $customer->id) }}" method="POST">
                                @csrf
                    <div class="p-6 space-y-4">
                                    <!-- Snapshot Biaya Info -->
                                    @php
                                        $svc = $customer->customerService;
                                        $svcMonthly = (float)$svc->monthly_price;
                                        $svcDiscount = (float)($svc->discount ?? 0);
                                        $svcPpnRate = (float)($svc->ppn ?? 0);
                                        $svcAfterDiscount = max(0, $svcMonthly - $svcDiscount);
                                        $svcPpnAmount = round($svcAfterDiscount * ($svcPpnRate / 100), 2);
                                        $svcTotal = $svcAfterDiscount + $svcPpnAmount;
                                    @endphp
                                    <div class="p-4 bg-slate-50 border border-slate-100 rounded-lg text-xs">
                                        <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-2">Rincian Paket & Layanan</span>
                                        <div class="space-y-1.5 font-medium text-slate-700">
                                            <div class="flex justify-between">
                                                <span class="text-slate-500">Paket Internet:</span>
                                                <span class="font-semibold text-slate-900">{{ $svc->package_name_snapshot }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-slate-500">Harga Bulanan:</span>
                                                <span class="font-mono" id="modal-monthly-price">Rp {{ number_format($svcMonthly, 0, ',', '.') }}</span>
                                            </div>
                                            @if($svcDiscount > 0)
                                                <div class="flex justify-between text-green-600">
                                                    <span>Potongan Diskon:</span>
                                                    <span class="font-mono">- Rp {{ number_format($svcDiscount, 0, ',', '.') }}</span>
                                                </div>
                                            @endif
                                            @if($svcPpnRate > 0)
                                                <div class="flex justify-between">
                                                    <span class="text-slate-500">PPN ({{ number_format($svcPpnRate, 0) }}%):</span>
                                                    <span class="font-mono">Rp {{ number_format($svcPpnAmount, 0, ',', '.') }}</span>
                                                </div>
                                            @endif
                                            <hr class="border-dashed border-slate-200 my-1.5">
                                            <div class="flex justify-between font-bold text-slate-900">
                                                <span>Total Tagihan Nett:</span>
                                                <span class="font-mono text-sky-600">Rp {{ number_format($svcTotal, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label for="billing_period" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Periode Tagihan (Bulan/Tahun)</label>
                                        <input type="month" name="billing_period" id="billing_period" value="{{ $defaultPeriod }}" required
                                               class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                                    </div>

                                    <div>
                                        <label for="issue_date" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Tanggal Terbit</label>
                                        <input type="date" name="issue_date" id="issue_date" value="{{ $defaultIssueDate }}" required
                                               class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                                    </div>

                                    <div>
                                        <label for="due_date" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Tanggal Jatuh Tempo</label>
                                        <input type="date" name="due_date" id="due_date" value="{{ $defaultDueDate }}" required
                                               class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                                    </div>

                                    <div>
                                        <label for="invoice_type" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                            Jenis Tagihan
                                            <span class="text-slate-400 font-normal normal-case">— pilih manual, jangan andalkan tebakan sistem</span>
                                        </label>
                                        <select name="invoice_type" id="invoice_type" required
                                                class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-semibold">
                                            <option value="bulanan" {{ $customer->invoices->count() > 0 ? 'selected' : '' }}>Tagihan Bulanan Rutin</option>
                                            <option value="awal" {{ $customer->invoices->count() === 0 ? 'selected' : '' }}>Tagihan Awal (PSB)</option>
                                            <option value="reaktivasi">Tagihan Reaktivasi</option>
                                        </select>
                                    </div>

                                    <!-- Biaya Tambahan Di Luar Standar -->
                                    <div class="border-t border-slate-100 pt-4">
                                        <div class="flex items-center justify-between mb-3">
                                            <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider">Biaya Tambahan (Opsional)</span>
                                            @if($customer->customerService?->activation_date)
                                                <button type="button" onclick="autoCalculateProrate()" class="text-[9px] font-bold text-sky-600 hover:text-sky-700 uppercase tracking-wider cursor-pointer">
                                                    Hitung Otomatis Prorate
                                                </button>
                                            @endif
                                        </div>
                                        <div class="space-y-3">
                                            <div>
                                                <label for="prorate_amount" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                                    Tagihan Prorate
                                                    <span class="text-slate-400 font-normal normal-case">— biaya hari aktivasi s/d akhir bulan</span>
                                                </label>
                                                <input type="number" name="prorate_amount" id="prorate_amount" value="{{ old('prorate_amount', 0) }}" min="0" step="1"
                                                       class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono"
                                                       oninput="recalcInvoiceTotal()">
                                            </div>
                                            <div>
                                                <label for="extra_installation_fee" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Biaya Pemasangan / Jasa Instalasi</label>
                                                @php
                                                    $defaultInstallFee = 0;
                                                    if ($customer->invoices->count() === 0 && $customer->internetPackage) {
                                                        $defaultInstallFee = (float)$customer->internetPackage->installation_fee;
                                                    }
                                                @endphp
                                                <input type="number" name="extra_installation_fee" id="extra_installation_fee" value="{{ old('extra_installation_fee', $defaultInstallFee) }}" min="0" step="1"
                                                       class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono"
                                                       oninput="recalcInvoiceTotal()">
                                                @if($defaultInstallFee > 0)
                                                    <p class="text-[9px] text-slate-400 mt-1 italic">Default dari Master Paket: Rp {{ number_format($defaultInstallFee, 0, ',', '.') }}</p>
                                                @endif
                                            </div>
                                            <div>
                                                <label for="extra_cable_fee" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Biaya Kabel Tambahan</label>
                                                <input type="number" name="extra_cable_fee" id="extra_cable_fee" value="{{ old('extra_cable_fee', 0) }}" min="0" step="1"
                                                       class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono"
                                                       oninput="recalcInvoiceTotal()">
                                            </div>
                                            <div>
                                                <label for="extra_pole_fee" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Tambahan Tiang</label>
                                                <input type="number" name="extra_pole_fee" id="extra_pole_fee" value="{{ old('extra_pole_fee', 0) }}" min="0" step="1"
                                                       class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono"
                                                       oninput="recalcInvoiceTotal()">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Preview Total -->
                                    <div class="bg-sky-50 border border-sky-100 rounded-lg p-4 text-xs">
                                        <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-2">Preview Total Tagihan</span>
                                        <div class="space-y-1.5">
                                            <div class="flex justify-between text-slate-600">
                                                <span>Tagihan Bulanan Nett:</span>
                                                <span class="font-mono" id="preview-nett">Rp {{ number_format($svcTotal, 0, ',', '.') }}</span>
                                            </div>
                                            <div class="flex justify-between text-slate-600" id="preview-prorate-row" style="display:none!important">
                                                <span>+ Prorate:</span>
                                                <span class="font-mono" id="preview-prorate">Rp 0</span>
                                            </div>
                                            <div class="flex justify-between text-slate-600" id="preview-cable-row" style="display:none!important">
                                                <span>+ Kabel:</span>
                                                <span class="font-mono" id="preview-cable">Rp 0</span>
                                            </div>
                                            <div class="flex justify-between text-slate-600" id="preview-install-row" style="display:none!important">
                                                <span>+ Instalasi:</span>
                                                <span class="font-mono" id="preview-install">Rp 0</span>
                                            </div>
                                            <div class="flex justify-between text-slate-600" id="preview-pole-row" style="display:none!important">
                                                <span>+ Tiang:</span>
                                                <span class="font-mono" id="preview-pole">Rp 0</span>
                                            </div>
                                            <hr class="border-dashed border-sky-200">
                                            <div class="flex justify-between font-bold text-slate-900 text-sm">
                                                <span>Total Bayar:</span>
                                                <span class="font-mono text-sky-700" id="preview-total">Rp {{ number_format($svcTotal, 0, ',', '.') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex justify-end gap-2 text-xs">
                                    <button type="button" onclick="closeInvoiceModal()" class="px-3 py-1.5 border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                                        Batal
                                    </button>
                                    <button type="submit" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                                        Proses Tagihan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan

            @include('payments.partials.quick-payment-modal')

            <!-- Tab 8: Pembayaran -->
            <div id="tab-content-pembayaran" class="tab-content hidden space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Riwayat Pembayaran Pelanggan</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Pembayaran yang terhubung ke invoice pelanggan ini.</p>
                    </div>
                </div>

                @if($customer->payments && $customer->payments->count() > 0)
                    <div class="overflow-x-auto border border-slate-200 rounded-lg shadow-sm">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 font-semibold text-slate-600 uppercase tracking-wider text-[10px]">
                                    <th class="px-4 py-3">No. Pembayaran</th>
                                    <th class="px-4 py-3">No. Tagihan</th>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Metode</th>
                                    <th class="px-4 py-3 text-right">Nominal</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Bukti</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-700">
                                @foreach($customer->payments as $payment)
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-4 py-3 font-mono font-bold text-slate-800">{{ $payment->payment_number }}</td>
                                        <td class="px-4 py-3 font-mono">
                                            @can('invoices.view')
                                                <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="text-sky-700 hover:text-sky-900">{{ $payment->invoice->invoice_number ?? '-' }}</a>
                                            @else
                                                {{ $payment->invoice->invoice_number ?? '-' }}
                                            @endcan
                                        </td>
                                        <td class="px-4 py-3">{{ \App\Support\IndonesianDate::date($payment->payment_date) }}</td>
                                        <td class="px-4 py-3">{{ strtoupper($payment->payment_method) }}</td>
                                        <td class="px-4 py-3 text-right font-mono font-semibold">Rp {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide bg-green-50 text-green-700 border-green-200">
                                                {{ $payment->payment_status->label() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($payment->proof_file)
                                                <a href="{{ asset('storage/' . $payment->proof_file) }}" target="_blank" class="text-sky-700 hover:text-sky-900 font-semibold">Lihat bukti</a>
                                            @else
                                                <span class="text-slate-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-slate-400 bg-slate-50/20 border border-dashed border-slate-200 rounded-lg">
                        <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <h4 class="text-sm font-semibold text-slate-700">Belum ada riwayat pembayaran</h4>
                        <p class="text-xs text-slate-500 mt-1">Pembayaran akan tampil setelah finance mencatat pembayaran invoice.</p>
                    </div>
                @endif
            </div>

            <!-- Tab 9: Dokumen -->
            <div id="tab-content-dokumen" class="tab-content hidden space-y-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">LAMPIRAN DOKUMEN PENDUKUNG</span>
                        <p class="text-xs text-slate-500">Dokumen disimpan private dan hanya bisa dibuka oleh user dengan permission dokumen pelanggan.</p>
                    </div>
                </div>

                @if(auth()->user()->hasPermission('customers.detail.documents.view'))
                    @if(auth()->user()->hasPermission('upload_customer_documents'))
                        <form method="POST" action="{{ route('customers.documents.store', ['customer' => $customer->id]) }}" enctype="multipart/form-data" class="border border-slate-200 rounded-lg p-4 bg-slate-50">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                                <div>
                                    <label for="document_type" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Jenis Dokumen</label>
                                    <select name="document_type" id="document_type" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" required>
                                        @foreach(\App\Enums\DocumentType::cases() as $type)
                                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="document_file" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">File</label>
                                    <input type="file" name="document_file" id="document_file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" capture="environment" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" required>
                                    <p class="text-[10px] text-slate-400 mt-1">Format: JPG, PNG, WEBP, PDF. Maksimal 4 MB.</p>
                                </div>
                                <div>
                                    <button type="submit" class="w-full px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-md uppercase tracking-wide transition-colors">
                                        Upload Dokumen
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif

                    @if($customer->documents->isNotEmpty())
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($customer->documents->sortByDesc('created_at') as $document)
                                <div class="border border-slate-200 rounded-lg overflow-hidden flex flex-col justify-between hover:border-sky-300 transition-colors shadow-sm bg-white">
                                    <div class="p-4 bg-slate-50 flex items-center justify-center h-36 border-b border-slate-100">
                                        @if($document->isImage())
                                            <img src="{{ route('customers.documents.show', $document->id) }}" alt="{{ $document->typeLabel() }}" class="max-h-28 max-w-full rounded object-contain shadow-sm">
                                        @else
                                            <div class="h-28 w-28 bg-red-50 border border-red-200 rounded-lg flex flex-col items-center justify-center text-red-600 shadow-sm">
                                                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                                <span class="text-[10px] font-bold mt-2">DOKUMEN PDF</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="p-3 bg-white flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <span class="block text-xs font-semibold text-slate-800 truncate">{{ $document->typeLabel() }}</span>
                                            <span class="block text-[9px] text-slate-400 mt-0.5 font-mono">{{ $document->created_at?->format('d/m/Y H:i') }}</span>
                                            <span class="block text-[9px] text-slate-400 mt-0.5 truncate">Upload: {{ $document->uploader?->name ?? '-' }}</span>
                                        </div>
                                        <a href="{{ route('customers.documents.show', $document->id) }}" target="_blank" class="shrink-0 p-1.5 text-sky-600 hover:text-sky-800 hover:bg-slate-100 rounded cursor-pointer transition-colors" title="Buka Dokumen">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0A9 9 0 113 12a9 9 0 0118 0z" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="py-12 text-center text-slate-400 bg-slate-50/20 border border-dashed border-slate-200 rounded-lg">
                            <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h4 class="text-sm font-semibold text-slate-700">Belum ada dokumen pelanggan</h4>
                            <p class="text-xs text-slate-500 mt-1">Dokumen KTP, rumah, kontrak, survey, dan pemasangan akan tampil setelah diupload.</p>
                        </div>
                    @endif
                @else
                    <div class="py-12 text-center text-slate-400 bg-slate-50/20 border border-dashed border-slate-200 rounded-lg">
                        <h4 class="text-sm font-semibold text-slate-700">Akses dokumen dibatasi</h4>
                        <p class="text-xs text-slate-500 mt-1">User Anda tidak memiliki permission untuk melihat dokumen pelanggan.</p>
                    </div>
                @endif
            </div>

            <!-- Tab: Riwayat Ticketing -->
            <div id="tab-content-riwayat-ticketing" class="tab-content hidden space-y-6">
                @include('customers.tabs._riwayat_ticketing')
            </div>

            <!-- Tab: Riwayat Perubahan -->
            <div id="tab-content-riwayat-perubahan" class="tab-content hidden space-y-6">
                @include('customers.tabs._riwayat_perubahan')
            </div>

            @if($customer->customerTechnicalDetail)
            <!-- Tab: Detail Teknis Lama -->
            <div id="tab-content-teknis-lama" class="tab-content hidden space-y-6">
                <div class="border border-slate-200 rounded-lg overflow-hidden bg-white shadow-sm">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200">
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-wider">Detail Teknis Jaringan Lama (Hasil Migrasi)</span>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-xs">
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">ID Report Lama</span>
                            <span class="font-mono font-bold text-slate-800">{{ $customer->customerTechnicalDetail->old_report_id }}</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">ID Request/Layanan Lama</span>
                            <span class="font-mono font-semibold text-slate-800">{{ $customer->customerTechnicalDetail->old_request_id ?? '-' }}</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">Tipe Koneksi</span>
                            <span class="font-semibold text-slate-800">{{ $customer->customerTechnicalDetail->connection_type ?? '-' }}</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">ONT Serial Number</span>
                            <span class="font-mono font-semibold text-slate-800">{{ $customer->customerTechnicalDetail->router_or_ont_serial ?? '-' }}</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">IP Address</span>
                            <span class="font-mono font-semibold text-slate-800">{{ $customer->customerTechnicalDetail->ip_address ?? '-' }}</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">SSID WiFi</span>
                            <span class="font-semibold text-slate-800">{{ $customer->customerTechnicalDetail->ssid ?? '-' }}</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">Antenna MAC</span>
                            <span class="font-mono text-slate-800">{{ $customer->customerTechnicalDetail->antenna_mac ?? '-' }}</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">Router MAC</span>
                            <span class="font-mono text-slate-800">{{ $customer->customerTechnicalDetail->router_mac ?? '-' }}</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">Nomor ODP / Port</span>
                            <span class="font-semibold text-slate-800">{{ $customer->customerTechnicalDetail->odp_number ?? '-' }} / {{ $customer->customerTechnicalDetail->odp_port ?? '-' }}</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">Port OLT</span>
                            <span class="font-semibold text-slate-800">{{ $customer->customerTechnicalDetail->olt_port ?? '-' }}</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">Signal Wireless / Kabel</span>
                            <span class="font-semibold text-slate-800">{{ $customer->customerTechnicalDetail->wireless_signal ?? '-' }} / {{ $customer->customerTechnicalDetail->fiber_signal ?? '-' }}</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">Lokasi Pemancar / Source</span>
                            <span class="font-semibold text-slate-800">{{ $customer->customerTechnicalDetail->location_source ?? '-' }}</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between">
                            <span class="text-slate-400">Hasil Test Speed</span>
                            <span class="font-mono font-semibold text-sky-600">{{ $customer->customerTechnicalDetail->test_download ?? '-' }} Mbps Down / {{ $customer->customerTechnicalDetail->test_upload ?? '-' }} Mbps Up</span>
                        </div>
                        <div class="py-2 border-b border-slate-100 flex justify-between md:col-span-2">
                            <span class="text-slate-400">Catatan Teknis Pemasangan</span>
                            <span class="text-slate-800 font-semibold">{{ $customer->customerTechnicalDetail->note ?? '-' }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @endif

        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    function switchTab(tabId) {
        // Hide all tab panels
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
        });
        
        // Show active tab panel
        document.getElementById('tab-content-' + tabId).classList.remove('hidden');

        // Reset active state for buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('border-sky-600', 'text-sky-600');
            btn.classList.add('border-transparent', 'text-slate-500');
        });

        // Add active state to clicked button
        const activeBtn = document.getElementById('tab-btn-' + tabId);
        activeBtn.classList.add('border-sky-600', 'text-sky-600');
        activeBtn.classList.remove('border-transparent', 'text-slate-500');
    }

    function openInvoiceModal() {
        const modal = document.getElementById('manual-invoice-modal');
        if (modal) {
            modal.classList.remove('hidden');
            recalcInvoiceTotal(); // Recalculate initially for pre-filled fees
        }
    }

    function closeInvoiceModal() {
        const modal = document.getElementById('manual-invoice-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    // Recalculate invoice total preview saat biaya tambahan diubah
    const BASE_NETT = {{ (float)$customer->customerService?->total_monthly_bill ?? 0 }};
    function recalcInvoiceTotal() {
        const prorate = parseFloat(document.getElementById('prorate_amount')?.value || 0) || 0;
        const cable   = parseFloat(document.getElementById('extra_cable_fee')?.value || 0) || 0;
        const install = parseFloat(document.getElementById('extra_installation_fee')?.value || 0) || 0;
        const pole    = parseFloat(document.getElementById('extra_pole_fee')?.value || 0) || 0;
        const total   = BASE_NETT + prorate + cable + install + pole;

        const fmt = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
        const showRow = (rowId, valId, label, v) => {
            const row = document.getElementById(rowId);
            const el  = document.getElementById(valId);
            if (row && el) {
                row.style.display = v > 0 ? 'flex' : 'none';
                el.textContent = fmt(v);
            }
        };

        showRow('preview-prorate-row', 'preview-prorate', 'Prorate', prorate);
        showRow('preview-cable-row',   'preview-cable',   'Kabel',   cable);
        showRow('preview-install-row', 'preview-install', 'Instalasi', install);
        showRow('preview-pole-row',    'preview-pole',    'Tiang',   pole);

        const totalEl = document.getElementById('preview-total');
        if (totalEl) totalEl.textContent = fmt(total);
    }

    function autoCalculateProrate() {
        const activationDateStr = "{{ $customer->customerService?->activation_date }}";
        if (!activationDateStr) {
            window.Toast.warning('Peringatan', 'Tanggal aktivasi belum diisi di sistem. Silakan pastikan data aktivasi sudah ada.');
            return;
        }

        // Activation date from DB (Y-m-d)
        const activationDate = new Date(activationDateStr);
        const year = activationDate.getFullYear();
        const month = activationDate.getMonth();
        
        // Last day of that specific month
        const lastDayOfMonth = new Date(year, month + 1, 0).getDate();
        const startDay = activationDate.getDate();
        
        // Days remaining in month (inclusive of activation day)
        // Logic: (LastDay - StartDay) + 1
        const daysRemaining = (lastDayOfMonth - startDay) + 1;
        
        // Calculate: (Monthly Bill / 30) * Days
        const monthlyBill = {{ (float)$customer->customerService?->total_monthly_bill ?? 0 }};
        const prorate = (monthlyBill / 30) * daysRemaining;
        
        const prorateInput = document.getElementById('prorate_amount');
        if (prorateInput) {
            prorateInput.value = Math.round(prorate);
            recalcInvoiceTotal();
        }
    }

    function openSurveyModal() {
        const modal = document.getElementById('survey-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeSurveyModal() {
        const modal = document.getElementById('survey-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function openInstallationModal() {
        const modal = document.getElementById('installation-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeInstallationModal() {
        const modal = document.getElementById('installation-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function openTestReportModal() {
        const modal = document.getElementById('test-report-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeTestReportModal() {
        const modal = document.getElementById('test-report-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }

    function openDeviceModal() {
        const modal = document.getElementById('device-modal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }

    function closeDeviceModal() {
        const modal = document.getElementById('device-modal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
</script>

@if(auth()->user()->hasPermission('customers.detail.installation.validate'))
<x-ui.modal name="network-assignment" title="Atur Mini POP & Distribusi" maxWidth="sm">
    <div x-data="{ miniPopId: '{{ old('mini_pop_id', $customer->mini_pop_id) }}' }">
        {{-- Header Info Pelanggan (Naked/Borderless Style) --}}
        <div class="pb-3 mb-4 border-b border-border space-y-1">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-[10px] font-bold text-text-muted uppercase tracking-wider block">Pelanggan</span>
                    <span class="text-sm font-bold text-text-main">{{ $customer->full_name }}</span>
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-bold text-text-muted uppercase tracking-wider block">ID Jaringan</span>
                    <span class="font-mono text-xs text-text-main bg-surface-muted px-1.5 py-0.5 rounded border border-border">{{ $customer->cid ?? 'DRAFT' }}</span>
                </div>
            </div>
            <div class="text-xs text-text-muted pt-1 flex items-center gap-1">
                <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <span>Cabang: <strong class="text-text-secondary font-semibold">{{ $customer->pop->name ?? '-' }} ({{ $customer->pop->pop_code ?? '' }})</strong></span>
            </div>
        </div>

        {{-- Info Alert Banner --}}
        <div class="flex gap-2 text-xs text-text-muted leading-relaxed bg-primary-soft/30 p-2.5 rounded-md border border-primary-border/40 mb-4">
            <svg class="w-4 h-4 text-primary shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Mini POP (OLT) & Distribusi dapat diatur setelah pemasangan dimulai, dan disesuaikan dengan konfigurasi MikroTik aktual.</span>
        </div>

        <form action="{{ route('customers.network-assignment.update', $customer) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted">Mini POP (OLT)</label>
                <select name="mini_pop_id" x-model="miniPopId" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-md bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all duration-200 cursor-pointer">
                    <option value="">— Belum di-assign —</option>
                    @foreach($availableMiniPops as $miniPop)
                        <option value="{{ $miniPop->id }}">[{{ $miniPop->pop_code }}] {{ $miniPop->name }}</option>
                    @endforeach
                </select>
                @if($availableMiniPops->isEmpty())
                    <div class="flex gap-1.5 items-start mt-1 text-[11px] text-warning">
                        <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>Belum ada Mini POP terdaftar di bawah Cabang POP pelanggan ini — tambahkan dulu lewat Master POP.</span>
                    </div>
                @endif
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted">Distribusi</label>
                <select name="distribution_id" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-md bg-white focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all duration-200 cursor-pointer">
                    <option value="">— Belum di-assign —</option>
                    @foreach($availableDistributions as $dist)
                        <option value="{{ $dist->id }}"
                                x-show="miniPopId == {{ $dist->pop_id }}"
                                {{ old('distribution_id', $customer->distribution_id) == $dist->id ? 'selected' : '' }}>
                            [{{ $dist->code }}] {{ $dist->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-text-muted">Daftar Distribusi otomatis mengikuti Mini POP yang dipilih di atas.</p>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-border mt-4">
                <button type="button" @click="$dispatch('close-modal', 'network-assignment')"
                        class="text-sm font-semibold px-4 py-2 rounded-md border border-slate-200 bg-white hover:bg-slate-50 text-text-secondary transition-colors duration-200 cursor-pointer">Batal</button>
                <button type="submit" class="text-sm font-semibold px-5 py-2 rounded-md text-white bg-sky-600 hover:bg-sky-700 shadow-sm transition-colors duration-200 cursor-pointer">Simpan</button>
            </div>
        </form>
    </div>
</x-ui.modal>
@endif
@endsection

