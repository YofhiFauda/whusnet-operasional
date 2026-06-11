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
    $fieldNames = [
        'full_name' => 'Nama Lengkap',
        'identity_number' => 'NIK',
        'gender' => 'Jenis Kelamin',
        'phone' => 'Nomor HP',
        'email' => 'Email',
        'registration_date' => 'Tanggal Registrasi',
        'address' => 'Alamat Lengkap',
        'city_id' => 'Kota',
        'district_id' => 'Kecamatan',
        'village_id' => 'Desa',
        'latitude' => 'Latitude',
        'longitude' => 'Longitude',
        'internet_package_id' => 'Paket Internet',
        'contract_period_months' => 'Masa Kontrak',
        'discount_amount' => 'Diskon',
        'tax_percent' => 'PPN',
        'sales_code' => 'ID Sales',
        'agent_code' => 'ID Agent',
        'referral_customer_code' => 'ID Referral Pelanggan',
        'status' => 'Status Layanan',
        'ont_sn' => 'SN ONT',
        'ip_address' => 'IP Address',
        'odp_code' => 'ODP',
        'olt_code' => 'OLT',
        'vlan_id' => 'VLAN'
    ];
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
                <strong class="text-red-600">Kekurangan Data Wajib:</strong> {{ implode(', ', array_map(fn($f) => $fieldNames[$f] ?? $f, $completeness['missing_required'])) }}. Mohon segera lakukan pengeditan profil.
            @elseif(count($completeness['missing_optional']) > 0)
                <strong class="text-amber-600">Kekurangan Data Opsional:</strong> {{ implode(', ', array_map(fn($f) => $fieldNames[$f] ?? $f, $completeness['missing_optional'])) }}.
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

    <!-- RIGHT COLUMN: 12-Tab Area -->
    <div class="lg:col-span-3 flex flex-col">
        <!-- Tabs Buttons Nav -->
        <div class="border-b border-slate-200 bg-white rounded-t-lg overflow-x-auto flex shadow-sm scrollbar-none">
            <button onclick="switchTab('ringkasan')" id="tab-btn-ringkasan" class="tab-button px-4 py-3 text-xs font-semibold border-b-2 border-sky-600 text-sky-600 focus:outline-none cursor-pointer whitespace-nowrap">Ringkasan</button>
            <button onclick="switchTab('data-diri')" id="tab-btn-data-diri" class="tab-button px-4 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Data Diri</button>
            <button onclick="switchTab('dokumen')" id="tab-btn-dokumen" class="tab-button px-4 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Dokumen</button>
            <button onclick="switchTab('layanan')" id="tab-btn-layanan" class="tab-button px-4 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Layanan</button>
            <button onclick="switchTab('referral')" id="tab-btn-referral" class="tab-button px-4 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Referral</button>
            <button onclick="switchTab('survey')" id="tab-btn-survey" class="tab-button px-4 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Survey</button>
            <button onclick="switchTab('fop')" id="tab-btn-fop" class="tab-button px-4 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">FOP</button>
            <button onclick="switchTab('pemasangan')" id="tab-btn-pemasangan" class="tab-button px-4 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Pemasangan</button>
            <button onclick="switchTab('aktivasi')" id="tab-btn-aktivasi" class="tab-button px-4 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Aktivasi</button>
            <button onclick="switchTab('teknis')" id="tab-btn-teknis" class="tab-button px-4 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Teknis</button>
            <button onclick="switchTab('uji-layanan')" id="tab-btn-uji-layanan" class="tab-button px-4 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Uji Layanan</button>
            <button onclick="switchTab('pembayaran-awal')" id="tab-btn-pembayaran-awal" class="tab-button px-4 py-3 text-xs font-semibold border-b-2 border-transparent text-slate-500 hover:text-slate-800 focus:outline-none cursor-pointer whitespace-nowrap">Pembayaran Awal</button>
        </div>

        <!-- Tabs Content Body -->
        <div class="bg-white border-x border-b border-slate-200 rounded-b-lg p-6 min-h-[450px] shadow-sm">
            
            <!-- Tab 1: Ringkasan -->
            <div id="tab-content-ringkasan" class="tab-content space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Active Subscription card -->
                    <div class="border border-slate-100 rounded-lg p-4 bg-slate-50/50">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">LANGGANAN AKTIF</span>
                        @if($customer->internetPackage)
                            <h4 class="text-sm font-semibold text-slate-800">{{ $customer->internetPackage->package_code }} - {{ $customer->internetPackage->category }}</h4>
                            <p class="text-xs text-slate-500 mt-1">{{ $customer->internetPackage->package_group }}</p>
                            <div class="flex items-baseline mt-3">
                                <span class="text-lg font-bold text-slate-800 data-text">Rp {{ number_format($customer->internetPackage->monthly_price, 0, ',', '.') }}</span>
                                <span class="text-[10px] text-slate-400 ml-1">/bulan</span>
                            </div>
                        @else
                            <p class="text-slate-400 text-xs py-2">Belum memilih paket layanan</p>
                        @endif
                    </div>
                    
                    <!-- Address Card -->
                    <div class="border border-slate-100 rounded-lg p-4 bg-slate-50/50">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">ALAMAT INSTALASI</span>
                        <p class="text-xs text-slate-700 font-medium leading-relaxed">{{ $customer->address }}</p>
                        <p class="text-[10px] text-slate-500 mt-1.5">Kel. {{ $customer->village->name ?? '-' }}, Kec. {{ $customer->district->name ?? '-' }}, Ponorogo</p>
                        <div class="mt-2.5 flex items-center gap-1.5 font-mono text-[9px] text-slate-400 data-text">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                            <span>Lat/Long: {{ $survey['latitude'] }}, {{ $survey['longitude'] }}</span>
                        </div>
                    </div>
                </div>

                <!-- Technical summary card -->
                <div class="border border-slate-100 rounded-lg p-5">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3">RINGKASAN INTEGRASI TEKNIS</span>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs">
                        <div>
                            <span class="block text-[9px] font-bold text-slate-400">NOMER CID</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $technical['cid'] }}</span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-slate-400">REDAMAN AKTUAL</span>
                            @if($customer->status === 'active')
                                <span class="font-mono font-medium text-green-600 data-text">{{ $technical['actual_attenuation'] }}</span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-slate-400">IP ADDRESS</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $technical['ip_address'] }}</span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold text-slate-400">ONT SN</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $technical['sn'] }}</span>
                        </div>
                    </div>
                </div>

                <!-- Workflow Timelog & Signature Card -->
                <div class="border border-slate-100 rounded-lg p-5 bg-white">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">RIWAYAT PROSES & SIGNATURE</span>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 text-xs">
                        <div class="space-y-2.5">
                            <div class="flex justify-between py-1 border-b border-slate-50">
                                <span class="text-slate-500 font-medium">Tanggal Registrasi</span>
                                <span class="font-mono text-slate-800 data-text">{{ $workflowLog['registration']['date'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-50">
                                <span class="text-slate-500 font-medium">Tanggal Survey</span>
                                <span class="font-mono text-slate-800 data-text">{{ $workflowLog['survey']['date'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-50">
                                <span class="text-slate-500 font-medium">Tanggal Admin Filter</span>
                                <span class="font-mono text-slate-800 data-text">{{ $workflowLog['admin_filter']['date'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-50">
                                <span class="text-slate-500 font-medium">Tanggal Teknisi Proses</span>
                                <span class="font-mono text-slate-800 data-text">{{ $workflowLog['technician_process']['date'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-50">
                                <span class="text-slate-500 font-medium">Tanggal Diverifikasi</span>
                                <span class="font-mono text-slate-800 data-text">{{ $workflowLog['verification']['date'] }}</span>
                            </div>
                        </div>
                        <div class="space-y-2.5">
                            <div class="flex justify-between py-1 border-b border-slate-50">
                                <span class="text-slate-500 font-medium">Di Input Oleh</span>
                                <span class="font-semibold text-slate-800">{{ $workflowLog['registration']['user'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-50">
                                <span class="text-slate-500 font-medium">Di Survey Oleh</span>
                                <span class="font-semibold text-slate-800">{{ $workflowLog['survey']['user'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-50">
                                <span class="text-slate-500 font-medium">Di Filter Oleh</span>
                                <span class="font-semibold text-slate-800">{{ $workflowLog['admin_filter']['user'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-50">
                                <span class="text-slate-500 font-medium">Di Proses Oleh</span>
                                <span class="font-semibold text-slate-800">{{ $workflowLog['technician_process']['user'] }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-50">
                                <span class="text-slate-500 font-medium">Di Verifikasi Oleh</span>
                                <span class="font-semibold text-slate-800">{{ $workflowLog['verification']['user'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Data Diri -->
            <div id="tab-content-data-diri" class="tab-content hidden space-y-6">
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
                            <span class="text-slate-400">Nomor Identitas NIK</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $customer->identity_number ?? '3502' . $customer->id . '19020432' }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Jenis Kelamin</span>
                            <span class="font-semibold text-slate-800">{{ $customer->gender ?? 'Laki-laki' }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Nomor HP</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ $customer->phone }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Alamat Email</span>
                            <span class="font-semibold text-slate-800">{{ $customer->email }}</span>
                        </div>
                        <div class="py-1.5 border-b border-slate-50 flex justify-between">
                            <span class="text-slate-400">Tanggal Registrasi</span>
                            <span class="font-mono font-medium text-slate-800 data-text">{{ \App\Support\IndonesianDate::date($customer->registration_date) }}</span>
                        </div>
                        <div class="py-1.5 flex justify-between md:col-span-2">
                            <span class="text-slate-400">Alamat Instalasi</span>
                            <span class="font-semibold text-slate-800 text-right">{{ $customer->address }}, Kel. {{ $customer->village->name ?? '-' }}, Kec. {{ $customer->district->name ?? '-' }}, Ponorogo</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Dokumen -->
            <div id="tab-content-dokumen" class="tab-content hidden space-y-6">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">LAMPIRAN DOKUMEN</span>
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

            <!-- Tab 4: Layanan -->
            <div id="tab-content-layanan" class="tab-content hidden space-y-6">
                <div class="border border-slate-100 rounded-lg overflow-hidden mb-6">
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-100">
                        <span class="text-xs font-semibold text-slate-600">Paket Layanan Terdaftar</span>
                    </div>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs">
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase">KODE LAYANAN</span>
                            <span class="font-semibold text-slate-800 mt-1 block">{{ $customer->internetPackage->package_code ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase">KATEGORI LAYANAN</span>
                            <span class="font-semibold text-slate-800 mt-1 block">{{ $customer->internetPackage->category ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400 uppercase">MASA KONTRAK</span>
                            <span class="font-semibold text-slate-800 mt-1 block data-text">{{ $contractPeriod }} Bulan</span>
                        </div>
                    </div>
                </div>

                <!-- Billing breakdown math box -->
                <div class="border border-slate-200 rounded-lg p-6 bg-slate-50/50">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">BREAKDOWN TAGIHAN BULANAN</span>
                    <div class="space-y-3 max-w-md text-xs font-mono data-text">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Harga Paket Layanan</span>
                            <span class="text-slate-800">Rp {{ number_format($monthlyPrice, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between text-green-600">
                            <span>Diskon Promosi</span>
                            <span>- Rp {{ number_format($discountAmount, 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">PPN ({{ number_format($taxPercent, 0) }}%)</span>
                            <span>Rp {{ number_format($taxAmount, 2, ',', '.') }}</span>
                        </div>
                        <hr class="border-dashed border-slate-200">
                        <div class="flex justify-between text-sm font-bold text-slate-900">
                            <span>Total Biaya Bulanan</span>
                            <span>Rp {{ number_format($totalMonthlyCost, 2, ',', '.') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 5: Referral -->
            <div id="tab-content-referral" class="tab-content hidden space-y-6">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">INFORMASI REFERRAL</span>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Sales -->
                    <div class="border border-slate-100 rounded-lg p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 bg-sky-50 text-sky-600 rounded">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700">SALES</span>
                        </div>
                        <div class="text-xs space-y-2">
                            <div class="flex justify-between"><span class="text-slate-400">ID Sales</span><span class="font-mono data-text">{{ $referral['sales_id'] }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-400">Nama Sales</span><span class="font-semibold text-slate-800">{{ $referral['sales_name'] }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-400">Kontak</span><span class="font-mono data-text">{{ $referral['sales_phone'] }}</span></div>
                        </div>
                    </div>

                    <!-- Agent -->
                    <div class="border border-slate-100 rounded-lg p-5">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="p-1.5 bg-purple-50 text-purple-600 rounded">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700">AGENT REFERRAL</span>
                        </div>
                        <div class="text-xs space-y-2">
                            <div class="flex justify-between"><span class="text-slate-400">ID Agent</span><span class="font-mono data-text">{{ $referral['agent_id'] }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-400">Nama Agent</span><span class="font-semibold text-slate-800">{{ $referral['agent_name'] }}</span></div>
                            <div class="flex justify-between"><span class="text-slate-400">Kontak Agent</span><span class="font-mono data-text">{{ $referral['agent_phone'] }}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 6: Survey -->
            <div id="tab-content-survey" class="tab-content hidden space-y-6">
                <div class="border border-slate-100 rounded-lg p-5 bg-slate-50/50 flex items-center justify-between mb-4">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">STATUS SURVEY</span>
                        <h4 class="text-sm font-semibold text-slate-800 mt-0.5">{{ $survey['status'] }}</h4>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $survey['badge_class'] ?? 'bg-slate-50 text-slate-700 border border-slate-100' }}">{{ $survey['badge_text'] ?? 'Pending' }}</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                    <div class="space-y-3 border border-slate-100 rounded-lg p-4">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">JADWAL & DURASI</span>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">Waktu Mulai</span><span class="font-mono data-text">{{ $survey['start_date'] }}</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">Waktu Selesai</span><span class="font-mono data-text">{{ $survey['end_date'] }}</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">Durasi Survey</span><span class="font-semibold text-slate-800">{{ $survey['duration'] }}</span></div>
                    </div>

                    <div class="space-y-3 border border-slate-100 rounded-lg p-4">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">KEBUTUHAN MATERIAL & PETUGAS</span>
                        <div class="py-1"><span class="text-slate-400 block mb-1">Petugas Lapangan</span>
                            <div class="flex flex-wrap gap-1">
                                @foreach($survey['surveyors'] as $svr)
                                    <span class="px-2 py-0.5 bg-slate-100 rounded text-[10px] font-semibold text-slate-700">{{ $svr }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="py-1 border-t border-slate-100"><span class="text-slate-400 block mb-1">Kebutuhan Alat</span>
                            <div class="flex flex-wrap gap-1">
                                @foreach($survey['tools'] as $tool)
                                    <span class="px-2 py-0.5 bg-sky-50 text-sky-700 rounded text-[10px] font-semibold">{{ $tool }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 7: FOP -->
            <div id="tab-content-fop" class="tab-content hidden space-y-6">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">PENUGASAN FIELD OPERATIONS</span>
                <div class="border border-slate-100 rounded-lg p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs">
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400">ID PENUGASAN FOP</span>
                            <span class="font-mono font-semibold text-slate-800 mt-1 block data-text">{{ $fop['fop_id'] }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400">WAKTU TUGAS SURVEY</span>
                            <span class="font-mono font-semibold text-slate-800 mt-1 block data-text">{{ $fop['assigned_survey'] }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400">WAKTU TUGAS PEMASANGAN</span>
                            <span class="font-mono font-semibold text-slate-800 mt-1 block data-text">{{ $fop['assigned_installation'] }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 8: Pemasangan -->
            <div id="tab-content-pemasangan" class="tab-content hidden space-y-6">
                <div class="border border-slate-100 rounded-lg p-5 bg-slate-50/50 flex items-center justify-between mb-4">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">STATUS INSTALASI</span>
                        <h4 class="text-sm font-semibold text-slate-800 mt-0.5">{{ $installation['status'] }}</h4>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $installation['badge_class'] ?? 'bg-slate-50 text-slate-700 border border-slate-100' }}">{{ $installation['badge_text'] ?? 'Pending' }}</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                    <div class="space-y-3 border border-slate-100 rounded-lg p-4">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">JADWAL PEMASANGAN</span>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">Tanggal Pekerjaan</span><span class="font-mono data-text">{{ $installation['date'] }}</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">Jam Mulai</span><span class="font-mono data-text">{{ $installation['start_time'] }}</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">Jam Selesai</span><span class="font-mono data-text">{{ $installation['end_time'] }}</span></div>
                    </div>

                    <div class="space-y-3 border border-slate-100 rounded-lg p-4">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">MATERIAL TERKONSUMSI</span>
                        <p class="text-slate-700 font-medium leading-relaxed mb-2">{{ $installation['materials'] }}</p>
                        <div class="py-1.5 border-t border-slate-100"><span class="text-slate-400 block mb-1">Teknisi Bertugas</span>
                            <div class="flex flex-wrap gap-1">
                                @foreach($installation['technicians'] as $tech)
                                    <span class="px-2 py-0.5 bg-slate-100 rounded text-[10px] font-semibold text-slate-700">{{ $tech }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 9: Aktivasi -->
            <div id="tab-content-aktivasi" class="tab-content hidden space-y-6">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">LAPORAN AKTIVASI LAYANAN</span>
                <div class="border border-slate-100 rounded-lg p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-xs">
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400">WAKTU AKTIF LAYANAN</span>
                            <span class="font-mono font-semibold text-slate-800 mt-1 block data-text">{{ $activation['date'] }} {{ $activation['time'] }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400">PETUGAS NOC</span>
                            <span class="font-semibold text-slate-800 mt-1 block">{{ $activation['staff'] }}</span>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold text-slate-400">PROFIL PPPOE PPPoE</span>
                            <span class="font-mono font-semibold text-sky-600 mt-1 block data-text">{{ $activation['profile_pppoe'] }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 10: Teknis -->
            <div id="tab-content-teknis" class="tab-content hidden space-y-6">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">PARAMETER TEKNIS JARINGAN</span>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                    <!-- Devices Parameters -->
                    <div class="space-y-3 border border-slate-100 rounded-lg p-4">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">ONT & IP DIAL-UP</span>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">Customer ID</span><span class="font-mono font-semibold text-slate-800 data-text">{{ $technical['cid'] }}</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">IP Address</span><span class="font-mono font-semibold text-slate-800 data-text">{{ $technical['ip_address'] }}</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">ONT Serial Number</span><span class="font-mono font-semibold text-slate-800 data-text">{{ $technical['sn'] }}</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">VLAN ID</span><span class="font-mono font-semibold text-slate-800 data-text">{{ $technical['vlan'] }}</span></div>
                    </div>

                    <!-- Port OLT/ODP -->
                    <div class="space-y-3 border border-slate-100 rounded-lg p-4">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">POP & PORT OLT/ODP</span>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">Nama POP Jaringan</span><span class="font-semibold text-slate-800">{{ $technical['pop'] }}</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">Nomor OLT</span><span class="font-semibold text-slate-800">{{ $technical['olt'] }}</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">Nomor Port OLT</span><span class="font-mono text-slate-800 data-text">{{ $technical['olt_port'] }}</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">Kode Kotak ODP</span><span class="font-semibold text-slate-800">{{ $technical['odp'] }}</span></div>
                        <div class="flex justify-between py-1 border-b border-slate-50"><span class="text-slate-400">Nomor Port ODP</span><span class="font-mono text-slate-800 data-text">{{ $technical['odp_port'] }}</span></div>
                    </div>
                </div>
            </div>

            <!-- Tab 11: Uji Layanan -->
            <div id="tab-content-uji-layanan" class="tab-content hidden space-y-6">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">VALIDASI UJI SPEEDTEST</span>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Gauge 1: Download -->
                    <div class="border border-slate-100 rounded-lg p-5 text-center flex flex-col justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">SPEED DOWNLOAD</span>
                        <div class="my-4">
                            <span class="text-3xl font-extrabold text-sky-600 data-text">{{ $testReport['download'] }}</span>
                        </div>
                        <span class="text-[10px] text-slate-400">Laporan aktual validasi</span>
                    </div>

                    <!-- Gauge 2: Upload -->
                    <div class="border border-slate-100 rounded-lg p-5 text-center flex flex-col justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">SPEED UPLOAD</span>
                        <div class="my-4">
                            <span class="text-3xl font-extrabold text-sky-600 data-text">{{ $testReport['upload'] }}</span>
                        </div>
                        <span class="text-[10px] text-slate-400">Laporan aktual validasi</span>
                    </div>

                    <!-- Gauge 3: Quality Score -->
                    <div class="border border-slate-100 rounded-lg p-5 text-center flex flex-col justify-between">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">SKOR KUALITAS</span>
                        <div class="my-4">
                            <span class="text-2xl font-bold text-green-600">{{ $testReport['quality_score'] }}</span>
                        </div>
                        <span class="text-[10px] text-slate-400">Jitter: {{ $testReport['jitter'] }} | Latency: {{ $testReport['latency'] }}</span>
                    </div>
                </div>
            </div>

            <!-- Tab 12: Pembayaran Awal -->
            <div id="tab-content-pembayaran-awal" class="tab-content hidden space-y-6">
                <div class="border border-slate-100 rounded-lg p-5 bg-slate-50/50 flex items-center justify-between mb-4">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">INVOICE PEMBAYARAN AWAL</span>
                        <h4 class="text-sm font-semibold text-slate-800 mt-0.5 font-mono data-text">{{ $initialPayment['invoice_code'] }}</h4>
                    </div>
                    @if($initialPayment['status'] === 'Lunas')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-green-50 text-green-700 border border-green-100">Lunas</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-red-50 text-red-700 border border-red-100">Belum Lunas</span>
                    @endif
                </div>

                <div class="border border-slate-100 rounded-lg p-6 bg-slate-50/50">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">RINCIAN BIAYA INSTALASI</span>
                    <div class="space-y-3 max-w-lg text-xs font-mono data-text">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Biaya Pemasangan Standar</span>
                            <span class="text-slate-800">Rp {{ number_format($initialPayment['installation_fee'], 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Prorate Hari Aktif ({{ $initialPayment['active_days'] }} hari / {{ $initialPayment['days_in_month'] }} hari)</span>
                            <span class="text-slate-800">Rp {{ number_format($initialPayment['prorate_amount'], 2, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Kabel Tambahan di luar batas standar (5m)</span>
                            <span class="text-slate-800">Rp {{ number_format($initialPayment['extra_cable_fee'], 2, ',', '.') }}</span>
                        </div>
                        <hr class="border-dashed border-slate-200">
                        <div class="flex justify-between text-sm font-bold text-slate-900">
                            <span>Total Biaya Pembayaran Awal</span>
                            <span>Rp {{ number_format($initialPayment['total'], 2, ',', '.') }}</span>
                        </div>
                    </div>
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
