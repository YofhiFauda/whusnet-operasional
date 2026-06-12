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
                <span class="text-xs font-mono text-slate-400 mt-1.5 data-text">{{ $displayId }}</span>
                
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

            <!-- Actions shortcut -->
            <div class="mt-6 grid grid-cols-2 gap-2 pt-6 border-t border-slate-100">
                <button onclick="alert('[WA] Menghubungi WhatsApp...')" class="flex items-center justify-center gap-1.5 py-1.5 px-3 border border-slate-200 rounded text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer">
                    WhatsApp
                </button>
                <button onclick="alert('[PING] Memindai status ONT... Redaman: -18.25 dBm (Stabil)')" class="flex items-center justify-center gap-1.5 py-1.5 px-3 border border-slate-200 rounded text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer">
                    Cek ONT
                </button>
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
            <button onclick="switchTab('paket-layanan')" id="tab-btn-paket-layanan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Paket & Layanan</button>
            <button onclick="switchTab('billing')" id="tab-btn-billing" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Billing</button>
            <button onclick="switchTab('tagihan')" id="tab-btn-tagihan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Tagihan</button>
            <button onclick="switchTab('pembayaran')" id="tab-btn-pembayaran" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Pembayaran</button>
            <button onclick="switchTab('dokumen')" id="tab-btn-dokumen" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Dokumen</button>
            <button onclick="switchTab('riwayat-perubahan')" id="tab-btn-riwayat-perubahan" class="tab-button px-4 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Riwayat Perubahan</button>
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
                                <span class="text-lg font-bold text-slate-800 data-text">Rp {{ number_format($customer->customerService?->total_monthly_bill ?? (($customer->internetPackage->monthly_price - ($customer->discount_amount ?? 0)) * (1 + ($customer->tax_percent ?? 11) / 100)), 0, ',', '.') }}</span>
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
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">Nomer CID</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $customer->cid ?? 'Belum dialokasikan' }}</span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">POP Cabang</span>
                            <span class="font-medium text-slate-800">{{ $customer->pop->name ?? '-' }}</span>
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
                            <span class="font-semibold text-slate-800">{{ $customer->gender ?? 'Belum diisi' }}</span>
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
                                <span class="font-mono font-semibold text-slate-800 mt-1 block data-text">{{ $customer->internetPackage->package_code }}</span>
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
                            $monthlyPrice = (float)($customer->customerService?->monthly_price ?? ($customer->internetPackage?->monthly_price ?? 0));
                            $discount = (float)($customer->customerService?->discount ?? ($customer->discount_amount ?? 0));
                            $ppnPercent = (float)($customer->customerService?->ppn ?? ($customer->tax_percent ?? 11));
                            
                            $discountedPrice = max(0, $monthlyPrice - $discount);
                            $ppnAmount = round($discountedPrice * ($ppnPercent / 100), 2);
                            $totalBill = $discountedPrice + $ppnAmount;
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
                <div class="py-12 text-center text-slate-400">
                    <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h4 class="text-sm font-semibold text-slate-700">Belum ada tagihan terbit</h4>
                    <p class="text-xs text-slate-500 mt-1">Modul Billing Dasar (Sprint 5) belum aktif pada sistem operasional.</p>
                </div>
            </div>

            <!-- Tab 8: Pembayaran -->
            <div id="tab-content-pembayaran" class="tab-content hidden space-y-6">
                <div class="py-12 text-center text-slate-400">
                    <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                    <h4 class="text-sm font-semibold text-slate-700">Belum ada riwayat pembayaran</h4>
                    <p class="text-xs text-slate-500 mt-1">Modul Pencatatan Pembayaran (Sprint 6) belum aktif pada sistem operasional.</p>
                </div>
            </div>

            <!-- Tab 9: Dokumen -->
            <div id="tab-content-dokumen" class="tab-content hidden space-y-6">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">LAMPIRAN DOKUMEN PENDUKUNG</span>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <!-- Doc 1: KTP -->
                    <div class="border border-slate-200 rounded-lg overflow-hidden flex flex-col justify-between hover:border-sky-300 transition-colors shadow-sm bg-white">
                        <div class="p-4 bg-slate-50 flex items-center justify-center h-36 border-b border-slate-100 relative group">
                            @if($customer->foto_ktp)
                                <img src="{{ asset('storage/' . $customer->foto_ktp) }}" alt="Foto KTP" class="max-h-28 max-w-full rounded object-contain shadow-sm">
                            @else
                                <div class="text-center text-slate-400">
                                    <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M21 12h-6m6 4h-6" />
                                    </svg>
                                    <span class="block text-[10px] mt-2 font-semibold uppercase tracking-wider">Belum Diunggah</span>
                                </div>
                            @endif
                        </div>
                        <div class="p-3 bg-white flex items-center justify-between">
                            <div>
                                <span class="block text-xs font-semibold text-slate-800">Foto KTP</span>
                                <span class="block text-[9px] text-slate-400 mt-0.5 font-mono">{{ $customer->foto_ktp ? 'Tersimpan' : 'Kosong' }}</span>
                            </div>
                            @if($customer->foto_ktp)
                                <a href="{{ asset('storage/' . $customer->foto_ktp) }}" target="_blank" download class="p-1.5 text-sky-600 hover:text-sky-800 hover:bg-slate-100 rounded cursor-pointer transition-colors" title="Download Foto KTP">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Doc 2: Foto Rumah -->
                    <div class="border border-slate-200 rounded-lg overflow-hidden flex flex-col justify-between hover:border-sky-300 transition-colors shadow-sm bg-white">
                        <div class="p-4 bg-slate-50 flex items-center justify-center h-36 border-b border-slate-100 relative group">
                            @if($customer->foto_rumah)
                                <img src="{{ asset('storage/' . $customer->foto_rumah) }}" alt="Foto Rumah" class="max-h-28 max-w-full rounded object-contain shadow-sm">
                            @else
                                <div class="text-center text-slate-400">
                                    <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                    <span class="block text-[10px] mt-2 font-semibold uppercase tracking-wider">Belum Diunggah</span>
                                </div>
                            @endif
                        </div>
                        <div class="p-3 bg-white flex items-center justify-between">
                            <div>
                                <span class="block text-xs font-semibold text-slate-800">Foto Depan Rumah</span>
                                <span class="block text-[9px] text-slate-400 mt-0.5 font-mono">{{ $customer->foto_rumah ? 'Tersimpan' : 'Kosong' }}</span>
                            </div>
                            @if($customer->foto_rumah)
                                <a href="{{ asset('storage/' . $customer->foto_rumah) }}" target="_blank" download class="p-1.5 text-sky-600 hover:text-sky-800 hover:bg-slate-100 rounded cursor-pointer transition-colors" title="Download Foto Rumah">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Doc 3: Foto Kontrak -->
                    <div class="border border-slate-200 rounded-lg overflow-hidden flex flex-col justify-between hover:border-sky-300 transition-colors shadow-sm bg-white">
                        <div class="p-4 bg-slate-50 flex items-center justify-center h-36 border-b border-slate-100 relative group">
                            @if($customer->foto_kontrak)
                                @if(Str::endsWith(strtolower($customer->foto_kontrak), '.pdf'))
                                    <!-- PDF Icon -->
                                    <div class="h-28 w-28 bg-red-50 border border-red-200 rounded-lg flex flex-col items-center justify-center text-red-600 shadow-sm">
                                        <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <span class="text-[10px] font-bold mt-2">DOKUMEN PDF</span>
                                    </div>
                                @else
                                    <img src="{{ asset('storage/' . $customer->foto_kontrak) }}" alt="Foto Kontrak" class="max-h-28 max-w-full rounded object-contain shadow-sm">
                                @endif
                            @else
                                <div class="text-center text-slate-400">
                                    <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="block text-[10px] mt-2 font-semibold uppercase tracking-wider">Belum Diunggah</span>
                                </div>
                            @endif
                        </div>
                        <div class="p-3 bg-white flex items-center justify-between">
                            <div>
                                <span class="block text-xs font-semibold text-slate-800">Foto Kontrak</span>
                                <span class="block text-[9px] text-slate-400 mt-0.5 font-mono">{{ $customer->foto_kontrak ? 'Tersimpan' : 'Kosong' }}</span>
                            </div>
                            @if($customer->foto_kontrak)
                                <a href="{{ asset('storage/' . $customer->foto_kontrak) }}" target="_blank" download class="p-1.5 text-sky-600 hover:text-sky-800 hover:bg-slate-100 rounded cursor-pointer transition-colors" title="Download Foto Kontrak">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 10: Riwayat Perubahan -->
            <div id="tab-content-riwayat-perubahan" class="tab-content hidden space-y-6">
                <div class="py-12 text-center text-slate-400">
                    <svg class="mx-auto h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h4 class="text-sm font-semibold text-slate-700">Belum ada riwayat perubahan data</h4>
                    <p class="text-xs text-slate-500 mt-1">Audit log umum (Sprint 8) belum aktif pada sistem operasional.</p>
                </div>
            </div>

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
</script>
@endsection
