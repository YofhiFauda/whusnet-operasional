@extends('layouts.app')

@section('title', 'List Pelanggan - Whusnet Operasional')
@section('page_title', 'List Pelanggan')

@section('content')
<style>
    .toggle-checkbox:checked + .toggle-label .check-icon {
        display: block;
    }
    .toggle-checkbox:checked + .toggle-label .x-icon {
        display: none;
    }
    .toggle-checkbox:not(:checked) + .toggle-label .check-icon {
        display: none;
    }
    .toggle-checkbox:not(:checked) + .toggle-label .x-icon {
        display: block;
    }
</style>
<!-- Page Header (Naked Header - Design.md §1.7 & §6.1) -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Pelanggan</h1>
        <p class="text-xs sm:text-sm text-slate-500 font-normal">Kelola data pelanggan, layanan aktif, status billing, dan dokumen.</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        @if(auth()->user()->hasPermission('customers.import.view'))
        <a href="/customers/import" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold py-2 px-3.5 rounded-lg transition-colors inline-flex items-center gap-1.5 shadow-2xs cursor-pointer">
            <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>Import Pelanggan</span>
        </a>
        @endif
        @if(auth()->user()->hasPermission('customers.create'))
        <a href="/customers/create" class="bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold py-2 px-3.5 rounded-lg transition-colors inline-flex items-center gap-1.5 shadow-2xs cursor-pointer">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>Tambah Pelanggan</span>
        </a>
        @endif
    </div>
</div>

<!-- Filter & Search Bar (Naked Bar - Design.md §1.8 & §1.5) -->
<div class="mb-5">
    <form action="/customers" method="GET" class="flex flex-wrap items-center gap-3">
        @if($statusGroup !== '')
            <input type="hidden" name="status_group" value="{{ $statusGroup }}">
        @endif
        @if($status !== '')
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        
        <!-- Search Input (Pill Shape with Icon - Design.md §1.8) -->
        <div class="relative flex-1 min-w-[240px] max-w-md">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Cari nama, CID, No. HP, atau ID Lama..." class="w-full text-xs pl-9 pr-3 py-2 border border-slate-200 rounded-full bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 font-sans shadow-2xs">
        </div>

        <!-- POP Filter -->
        <div class="min-w-[140px]">
            <select name="pop_id" id="pop_id" class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 font-sans shadow-2xs">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ $popId == $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <!-- Kecamatan Filter -->
        <div class="min-w-[140px]">
            <select name="district_id" id="district_id" class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 font-sans shadow-2xs">
                <option value="">Semua Kecamatan</option>
                @foreach($districts as $district)
                    <option value="{{ $district->id }}" {{ $districtId == $district->id ? 'selected' : '' }}>{{ $district->name }}</option>
                @endforeach
            </select>
        </div>

        <!-- Paket Layanan Filter -->
        <div class="min-w-[150px]">
            <select name="package_id" id="package_id" class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 font-sans shadow-2xs">
                <option value="">Semua Paket</option>
                @foreach($packages as $package)
                    <option value="{{ $package->id }}" {{ $packageId == $package->id ? 'selected' : '' }}>{{ $package->package_code }} - {{ $package->name }}</option>
                @endforeach
            </select>
        </div>

        <!-- Status Kelengkapan Filter -->
        <div class="min-w-[140px]">
            <select name="completeness_status" id="completeness_status" class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 font-sans shadow-2xs">
                <option value="">Semua Kelengkapan</option>
                <option value="draft" {{ $completenessStatus === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="perlu_dilengkapi" {{ $completenessStatus === 'perlu_dilengkapi' ? 'selected' : '' }}>Perlu Dilengkapi</option>
                <option value="lengkap" {{ $completenessStatus === 'lengkap' ? 'selected' : '' }}>Lengkap</option>
                <option value="siap_billing" {{ $completenessStatus === 'siap_billing' ? 'selected' : '' }}>Siap Billing</option>
            </select>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-1.5 shrink-0">
            <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold py-2 px-3.5 rounded-lg transition-colors cursor-pointer shadow-2xs">
                Cari
            </button>
            <a href="/customers" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold py-2 px-3.5 rounded-lg transition-colors cursor-pointer text-center">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Status Tabs Nav & Table Content -->
<div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
    @if(empty($statusGroup))
    <!-- Status Tabs Nav -->
    <div class="border-b border-slate-200 bg-slate-50 px-6 py-3 flex flex-wrap gap-2 items-center justify-between">
        <div class="flex flex-wrap gap-1">
            <a href="{{ request()->fullUrlWithQuery(['status' => '']) }}" class="px-3 py-1.5 rounded-md text-xs font-medium cursor-pointer transition-colors {{ $status === '' ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-200/50' }}">
                Semua <span class="ml-1 px-1.5 py-0.25 rounded-full text-[10px] {{ $status === '' ? 'bg-white/25 text-white' : 'bg-slate-200 text-slate-500' }} data-text">{{ $totalCustomers }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'active']) }}" class="px-3 py-1.5 rounded-md text-xs font-medium cursor-pointer transition-colors {{ $status === 'active' ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-200/50' }}">
                Active <span class="ml-1 px-1.5 py-0.25 rounded-full text-[10px] {{ $status === 'active' ? 'bg-white/25 text-white' : 'bg-slate-200 text-slate-500' }} data-text">{{ $statusCounts['active'] ?? 0 }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'suspended']) }}" class="px-3 py-1.5 rounded-md text-xs font-medium cursor-pointer transition-colors {{ $status === 'suspended' ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-200/50' }}">
                Suspend <span class="ml-1 px-1.5 py-0.25 rounded-full text-[10px] {{ $status === 'suspended' ? 'bg-white/25 text-white' : 'bg-slate-200 text-slate-500' }} data-text">{{ $statusCounts['suspended'] ?? 0 }}</span>
            </a>
        </div>
    </div>
    @else
    <!-- Filter Group Header -->
    <div class="border-b border-slate-200 bg-sky-50 px-6 py-3 flex flex-wrap gap-2 items-center justify-between">
        <div class="flex items-center gap-2">
            <span class="text-sm font-bold text-sky-800 uppercase tracking-wider">
                @if($statusGroup === 'survey') Daftar Survey Pelanggan
                @elseif($statusGroup === 'verification') Daftar Verifikasi Pelanggan
                @elseif($statusGroup === 'failed') Daftar Pelanggan Gagal
                @elseif($statusGroup === 'terminated') Daftar Pelanggan Putus
                @endif
            </span>
        </div>
    </div>
    @endif

    <!-- Table Container -->
    <div class="overflow-x-auto">
        @if($statusGroup === 'failed')
        <table class="w-full border-collapse text-left text-sm text-slate-700">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-200 text-slate-500 font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">CID</th>
                    <th class="px-6 py-3.5">NAMA</th>
                    <th class="px-6 py-3.5">POP</th>
                    <th class="px-6 py-3.5">ALASAN</th>
                    <th class="px-6 py-3.5">TGL PEMUTUSAN</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($customers as $customer)
                <tr class="hover:bg-slate-50/45 transition-colors">
                    <td class="px-6 py-3.5 text-center text-slate-400 data-text">
                        {{ ($customers->currentPage() - 1) * $customers->perPage() + $loop->iteration }}
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap data-text font-mono">
                        {{ $customer->display_id }}
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap font-medium text-slate-900">
                        {{ $customer->full_name }}
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap text-slate-800 font-medium">
                        {{ $customer->pop->name ?? '-' }}
                    </td>
                    <td class="px-6 py-3.5 max-w-xs text-slate-700">
                        {{ $customer->reject_reason ?? '-' }}
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap data-text">
                        {{ $customer->rejected_at ? \App\Support\IndonesianDate::date($customer->rejected_at) : '-' }}
                    </td>
                    <td class="px-6 py-3.5 text-right whitespace-nowrap">
                        <div class="inline-flex items-center gap-2">
                            <a href="{{ route('customers.show', $customer->id) }}"
                               class="inline-flex items-center text-xs font-medium text-sky-600 hover:text-sky-800 transition-colors border border-sky-200 hover:bg-sky-50 rounded px-2.5 py-1 cursor-pointer">
                                Detail
                            </a>
                            @if(auth()->user()->hasPermission('customers.detail.installation.validate') && $customer->status_before_reject)
                            <form action="{{ route('customers.restore-from-failed', $customer->id) }}" method="POST"
                                  onsubmit="event.preventDefault(); window.confirmAction('Apakah Anda yakin ingin mengembalikan {{ $customer->full_name }} ke proses sebelum ditolak?', this);">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center text-xs font-medium text-amber-600 hover:text-amber-800 transition-colors border border-amber-200 hover:bg-amber-50 rounded px-2.5 py-1 cursor-pointer">
                                    Kembalikan
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-8 text-center text-slate-400">
                        Tidak ada data pelanggan gagal.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @elseif($statusGroup === 'terminated')
        <table class="w-full border-collapse text-left text-sm text-slate-700">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-200 text-slate-500 font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">ID</th>
                    <th class="px-6 py-3.5">NAMA</th>
                    <th class="px-6 py-3.5">POP</th>
                    <th class="px-6 py-3.5">KONTRAK</th>
                    <th class="px-6 py-3.5">ALASAN PUTUS</th>
                    <th class="px-6 py-3.5">TGL PEMUTUSAN</th>
                    <th class="px-6 py-3.5 text-center">STATUS ALAT</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($customers as $customer)
                @php
                    $contractType = match($customer->customerService->contract_type ?? null) {
                        'sewa' => 'Sewa',
                        'beli' => 'Beli',
                        default => '-',
                    };
                    $isDeviceRetrieved = (bool) $customer->device_retrieved_at;
                @endphp
                <tr class="hover:bg-slate-50/45 transition-colors">
                    <td class="px-6 py-3.5 text-center text-slate-400 data-text">
                        {{ ($customers->currentPage() - 1) * $customers->perPage() + $loop->iteration }}
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap data-text font-mono">
                        {{ $customer->display_id }}
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap font-medium text-slate-900">
                        {{ $customer->full_name }}
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap text-slate-800 font-medium">
                        {{ $customer->pop->name ?? '-' }}
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap text-slate-800 font-medium">
                        {{ $contractType }}
                    </td>
                    <td class="px-6 py-3.5 max-w-xs text-slate-700">
                        {{ $customer->termination_reason ?? '-' }}
                    </td>
                    <td class="px-6 py-3.5 whitespace-nowrap data-text">
                        {{ $customer->terminated_at ? \App\Support\IndonesianDate::date($customer->terminated_at) : '-' }}
                    </td>
                    <td class="px-6 py-3.5 text-center whitespace-nowrap">
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border {{ $isDeviceRetrieved ? 'bg-green-50 text-green-700 border-green-100' : 'bg-amber-50 text-amber-700 border-amber-100' }}">
                            {{ $isDeviceRetrieved ? 'Sudah di Ambil' : 'Belum di Ambil' }}
                        </span>
                    </td>
                    <td class="px-6 py-3.5 text-right whitespace-nowrap">
                        <div class="inline-flex items-center gap-2">
                            <a href="{{ route('customers.show', $customer->id) }}"
                               class="inline-flex items-center text-xs font-medium text-sky-600 hover:text-sky-800 transition-colors border border-sky-200 hover:bg-sky-50 rounded px-2.5 py-1 cursor-pointer">
                                Detail
                            </a>
                            @if(!$isDeviceRetrieved && auth()->user()->hasPermission('customers.detail.devices.retrieve'))
                            <form action="{{ route('customers.retrieve-device', $customer->id) }}" method="POST"
                                  onsubmit="event.preventDefault(); window.confirmAction('Buat Task FOP pengambilan alat untuk {{ $customer->full_name }}?', this);">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center text-xs font-medium text-slate-600 hover:text-slate-800 transition-colors border border-slate-200 hover:bg-slate-50 rounded px-2.5 py-1 cursor-pointer">
                                    Ambil Alat
                                </button>
                            </form>
                            @endif
                            @if(auth()->user()->hasPermission('customers.detail.installation.validate'))
                            <form action="{{ route('customers.reactivate', $customer->id) }}" method="POST"
                                  onsubmit="event.preventDefault(); window.confirmAction('Aktifkan kembali langganan {{ $customer->full_name }}?', this);">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center text-xs font-medium text-emerald-600 hover:text-emerald-800 transition-colors border border-emerald-200 hover:bg-emerald-50 rounded px-2.5 py-1 cursor-pointer">
                                    Langganan Lagi
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-8 text-center text-slate-400">
                        Tidak ada data pelanggan putus langganan.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @else
        <table class="w-full border-collapse text-left text-sm text-slate-700">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-200 text-slate-500 font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">ID</th>
                    <th class="px-6 py-3.5">NAMA</th>
                    <th class="px-6 py-3.5">POP</th>
                    <th class="px-6 py-3.5">DESA</th>
                    <th class="px-6 py-3.5">PAKET</th>
                    <th class="px-6 py-3.5">HP</th>
                    <th class="px-6 py-3.5 text-center">KELENGKAPAN</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5 text-center">KONEKSI</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($customers as $customer)
                @php
                    $isCustomer = $customer->status === 'active';
                    $isTerminated = $customer->status === 'terminated';
                    // Gunakan accessor display_id dari Customer model
                    // yang menghitung format ID berdasarkan status pelanggan
                    // sesuai spesifikasi-pop-distribusi-cid.md
                    $displayId = $customer->display_id;
                    $completeness = $customer->dataCompleteness();
                    $stages = $customer->workflowProgress();
                @endphp
                <tr class="hover:bg-slate-50/45 transition-colors">
                    <!-- No -->
                    <td class="px-6 py-3.5 text-center text-slate-400 data-text">
                        {{ ($customers->currentPage() - 1) * $customers->perPage() + $loop->iteration }}
                    </td>
                    <!-- ID (CID / REQ) -->
                    <td class="px-6 py-3.5 whitespace-nowrap data-text font-mono">
                        @if(auth()->user()->hasPermission('customers.detail.installation.validate'))
                        <button type="button" onclick="openNetworkAssignmentModal({{ $customer->id }})"
                                class="text-primary hover:underline cursor-pointer" title="Atur Mini POP & Distribusi">
                            {{ $displayId }}
                        </button>
                        @else
                        {{ $displayId }}
                        @endif
                    </td>
                    <!-- Nama -->
                    <td class="px-6 py-3.5 whitespace-nowrap font-medium text-slate-900">
                        {{ $customer->full_name }}
                    </td>
                    <!-- POP -->
                    <td class="px-6 py-3.5 whitespace-nowrap text-slate-800 font-medium">
                        {{ $customer->pop->name ?? '-' }}
                    </td>
                    <!-- Desa -->
                    <td class="px-6 py-3.5 whitespace-nowrap text-slate-800 font-medium">
                        {{ $customer->village->name ?? ($customer->customerAddress->village ?? '-') }}
                    </td>
                    <!-- Paket -->
                    <td class="px-6 py-3.5 whitespace-nowrap text-slate-800 font-semibold text-xs">
                        {{ $customer->internetPackage ? $customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name : '-' }}
                    </td>
                    <!-- HP -->
                    <td class="px-6 py-3.5 whitespace-nowrap data-text font-mono">
                        {{ $customer->primary_phone ?? $customer->phone }}
                    </td>
                    <!-- Kelengkapan (Progress & Lifecycle Stage Indicator) -->
                    <td class="px-6 py-3.5 whitespace-nowrap">
                        <div class="flex flex-col gap-1.5 justify-center items-center">
                            <!-- Progress Bar & Percentage -->
                            <div class="flex items-center gap-1.5">
                                <div class="w-12 bg-slate-100 rounded-full h-1 overflow-hidden border border-slate-200/50">
                                    <div class="h-full rounded-full transition-all duration-300 {{ count($completeness['missing_required']) > 0 ? 'bg-red-500' : (count($completeness['missing_optional']) > 0 ? 'bg-amber-500' : 'bg-green-500') }}" style="width: {{ $completeness['percentage'] }}%"></div>
                                </div>
                                <span class="text-[9px] font-extrabold data-text {{ count($completeness['missing_required']) > 0 ? 'text-red-600' : (count($completeness['missing_optional']) > 0 ? 'text-amber-600' : 'text-green-600') }}">
                                    {{ $completeness['percentage'] }}%
                                </span>
                            </div>
                            
                            <!-- 5 Stage Dots -->
                            <div class="flex items-center gap-1">
                                @foreach($stages as $stageKey => $stage)
                                    <span class="h-3.5 w-3.5 rounded-full flex items-center justify-center text-[7px] font-extrabold text-white font-mono {{ $stage['color'] }} cursor-help" title="{{ $stage['name'] }}: {{ ucfirst($stage['status']) }}">
                                        {{ $stage['label'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </td>
                    <!-- Status Layanan -->
                    <td class="px-6 py-3.5 text-center whitespace-nowrap">
                        @php
                            $statusLabel = $customer->subscriptionStatus->name ?? ucfirst($customer->status);
                            $badgeClass = match($customer->status) {
                                'active' => 'bg-green-50 text-green-700 border border-green-100',
                                'suspended' => 'bg-amber-50 text-amber-700 border border-amber-100',
                                'terminated', 'rejected' => 'bg-red-50 text-red-700 border border-red-100',
                                'waiting_survey', 'surveyed' => 'bg-yellow-50 text-yellow-800 border border-yellow-100',
                                'waiting_installation', 'installed' => 'bg-blue-50 text-blue-700 border border-blue-100',
                                default => 'bg-slate-50 text-slate-700 border border-slate-100'
                            };
                        @endphp
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border {{ $badgeClass }}">
                            {{ $statusLabel }}
                        </span>
                    </td>
                    <!-- Koneksi with Toggle On Off -->
                    <td class="px-6 py-3.5 text-center whitespace-nowrap">
                        <div class="flex justify-center items-center">
                            <label class="relative inline-flex items-center cursor-pointer select-none">
                                <input type="checkbox" 
                                       class="sr-only peer toggle-checkbox" 
                                       {{ $customer->status === 'active' ? 'checked' : '' }}
                                       onchange="toggleConnection('{{ $customer->id }}', '{{ $customer->full_name }}', this)">
                                <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-focus:ring-2 peer-focus:ring-sky-500/25 peer-checked:bg-sky-600 transition-colors duration-200 relative toggle-label">
                                    <!-- Knob -->
                                    <div class="absolute top-[2px] left-[2px] w-5 h-5 bg-white rounded-full transition-transform duration-200 peer-checked:translate-x-5 flex items-center justify-center shadow-sm">
                                        <!-- X Icon -->
                                        <svg class="h-2.5 w-2.5 text-slate-400 x-icon transition-opacity duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <!-- Check Icon -->
                                        <svg class="h-2.5 w-2.5 text-sky-600 check-icon transition-opacity duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </td>
                    <!-- Action -->
                    <td class="px-6 py-3.5 text-right whitespace-nowrap">
                        <button type="button" 
                                onclick="openActionsModal(this)"
                                data-id="{{ $customer->id }}"
                                data-code="{{ $displayId }}"
                                data-raw-code="{{ $customer->customer_code }}"
                                data-name="{{ $customer->full_name }}"
                                data-nik="{{ $customer->identity_number ?? '-' }}"
                                data-phone="{{ $customer->primary_phone ?? $customer->phone }}"
                                data-email="{{ $customer->email ?? '-' }}"
                                data-status="{{ $customer->subscriptionStatus->name ?? Str::headline($customer->status) }}"
                                data-raw-status="{{ $customer->status }}"
                                data-pop="{{ $customer->pop->name ?? '-' }}"
                                data-reg="{{ \App\Support\IndonesianDate::date($customer->registration_date) }}"
                                data-package="{{ $customer->internetPackage ? $customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name : '-' }}"
                                data-bandwidth="{{ $customer->internetPackage->speed_mbps ? $customer->internetPackage->speed_mbps . ' Mbps' : '-' }}"
                                data-price="{{ $customer->internetPackage ? 'Rp ' . number_format($customer->internetPackage->monthly_price, 0, ',', '.') : '-' }}"
                                data-address="{{ $customer->address }}"
                                data-landmark="{{ $customer->customerAddress->landmark ?? '-' }}"
                                data-rt-rw="{{ ($customer->customerAddress->rt ? 'RT ' . $customer->customerAddress->rt : '') . ($customer->customerAddress->rw ? ' / RW ' . $customer->customerAddress->rw : '') ?: '-' }}"
                                data-village="{{ $customer->village->name ?? ($customer->customerAddress->village ?? '-') }}"
                                data-district="{{ $customer->district->name ?? ($customer->customerAddress->district ?? '-') }}"
                                data-city="{{ $customer->city->name ?? ($customer->customerAddress->city ?? 'Kab. Ponorogo') }}"
                                data-postal-code="{{ $customer->customerAddress->postal_code ?? '-' }}"
                                data-lat="{{ $customer->customerAddress->latitude ?? '' }}"
                                data-lng="{{ $customer->customerAddress->longitude ?? '' }}"
                                data-completeness-pct="{{ $completeness['percentage'] }}"
                                data-completeness-status="{{ Str::headline($customer->data_completeness_status ?? 'draft') }}"
                                data-pppoe="{{ $customer->customerService->pppoe_username ?? '-' }}"
                                data-ip="{{ $customer->customerService->ip_address ?? '-' }}"
                                data-vlan="{{ $customer->customerService->vlan_id ?? '-' }}"
                                data-onu="{{ $customer->customerDevice->onu_sn ?? ($customer->customerDevice->mac_address ?? '-') }}"
                                data-onu-brand="{{ $customer->customerDevice->onu_brand ?? '-' }}"
                                data-router="{{ $customer->customerDevice->router_sn ?? '-' }}"
                                data-router-brand="{{ $customer->customerDevice->router_brand ?? '-' }}"
                                data-contract="{{ match($customer->customerService->contract_type ?? null) { 'sewa' => 'Sewa', 'beli' => 'Beli', default => '-' } }}"
                                data-distribution="{{ $customer->distribution->name ?? '-' }}"
                                class="inline-flex items-center text-xs font-semibold text-sky-600 hover:text-sky-800 bg-sky-50/80 hover:bg-sky-100 transition-colors border border-sky-200 rounded-lg px-3 py-1.5 cursor-pointer shadow-2xs">
                            <svg class="w-3.5 h-3.5 mr-1 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            <span>Action</span>
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11" class="px-6 py-8 text-center text-slate-400">
                        Tidak ada data pelanggan yang cocok dengan pencarian Anda.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
        @endif
    </div>

    <!-- Pagination Footer -->
    @if($customers->hasPages())
    <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex items-center justify-between">
        <div class="flex-1 flex justify-between sm:hidden">
            {{ $customers->links('pagination::simple-tailwind') }}
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                {{ $customers->links() }}
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Customer Action & Quick Operational Hub Modal (Design.md Compliant) -->
<div id="actions-modal" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center hidden z-50 p-3 sm:p-5 transition-all duration-300 overflow-y-auto">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-3xl shadow-2xl overflow-hidden transform scale-95 transition-all duration-300 my-auto flex flex-col max-h-[92vh]">
        
        <!-- Modal Header (White Canvas / Naked Style - Design.md §1.3 & §6.4) -->
        <div class="px-6 py-4 bg-white border-b border-slate-200 shrink-0">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base sm:text-lg font-bold text-slate-900 tracking-tight" id="actions-modal-title">Nama Pelanggan</h3>
                        
                        <!-- CID Display Badge (JetBrains Mono - Design.md §13.2) -->
                        <span class="inline-flex items-center gap-1 font-mono text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200 rounded px-2 py-0.5" id="actions-modal-code">
                            ID-0000
                        </span>

                        <!-- Operational Status Badge with Dot (Design.md §13.1) -->
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200" id="actions-modal-status-badge">
                            <span class="w-1.5 h-1.5 rounded-full bg-current shrink-0"></span>
                            <span>ACTIVE</span>
                        </span>
                    </div>

                    <!-- Location & POP Meta Row -->
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 font-sans pt-0.5">
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <span id="actions-modal-location-text">Lokasi...</span>
                        </span>
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span id="actions-modal-pop-text" class="font-medium text-slate-700">POP Central</span>
                        </span>
                    </div>
                </div>

                <!-- Close Button -->
                <button onclick="closeActionsModal()" type="button" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors cursor-pointer shrink-0">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Quick Direct Operational Toolbar (Design.md §4.1 Toolbar Style) -->
            <div class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <!-- WA Direct -->
                    <a id="btn-quick-wa" href="#" target="_blank" class="inline-flex items-center gap-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors shadow-2xs">
                        <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.105 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                        <span>WA Direct</span>
                    </a>

                    <!-- Google Maps Direct -->
                    <a id="btn-quick-maps" href="#" target="_blank" class="inline-flex items-center gap-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        <span>Google Maps</span>
                    </a>

                    <!-- Copy Technical Info -->
                    <button onclick="copyTechInfo()" type="button" class="inline-flex items-center gap-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors cursor-pointer shadow-2xs">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span>Copy Teknis</span>
                    </button>
                </div>

                <!-- Completeness Badge -->
                <div class="flex items-center gap-1.5 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200 text-xs">
                    <span class="text-slate-500 font-medium">Kelengkapan Data:</span>
                    <span id="actions-modal-completeness-pct" class="font-extrabold text-sky-600 font-mono">0%</span>
                </div>
            </div>
        </div>

        <!-- Detail Tabs Navigation Bar (Design.md §6.4) -->
        <div class="bg-slate-50/80 border-b border-slate-200 px-6 pt-2 flex gap-1 font-sans text-xs select-none overflow-x-auto shrink-0" id="modal-tab-header">
            <button onclick="switchActionTab('finance')" id="tab-btn-finance" type="button" class="py-2.5 px-4 rounded-t-lg font-semibold flex items-center gap-2 border-b-2 border-sky-600 text-sky-600 bg-white transition-all cursor-pointer">
                <svg class="w-4 h-4 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                <span>1. Keuangan & Tagihan</span>
            </button>

            <button onclick="switchActionTab('technical')" id="tab-btn-technical" type="button" class="py-2.5 px-4 rounded-t-lg font-medium text-slate-500 hover:text-slate-900 transition-all cursor-pointer">
                <svg class="w-4 h-4 text-slate-400 shrink-0 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                <span>2. Teknis & Perangkat</span>
            </button>

            <button onclick="switchActionTab('field')" id="tab-btn-field" type="button" class="py-2.5 px-4 rounded-t-lg font-medium text-slate-500 hover:text-slate-900 transition-all cursor-pointer">
                <svg class="w-4 h-4 text-slate-400 shrink-0 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>3. Lokasi & Lapangan</span>
            </button>

            <button onclick="switchActionTab('profile')" id="tab-btn-profile" type="button" class="py-2.5 px-4 rounded-t-lg font-medium text-slate-500 hover:text-slate-900 transition-all cursor-pointer">
                <svg class="w-4 h-4 text-slate-400 shrink-0 inline mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span>4. Profil & Aksi</span>
            </button>
        </div>

        <!-- Body Canvas Container (Clean Canvas Style - Design.md §6.4 & §6.5) -->
        <div class="p-6 overflow-y-auto flex-1 bg-white">

            <!-- Loading Indicator -->
            <div id="modal-hub-loading" class="py-8 text-center hidden">
                <svg class="animate-spin h-6 w-6 text-sky-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-xs text-slate-500 font-medium mt-2">Sinkronisasi data tagihan & riwayat...</p>
            </div>

            <!-- TAB 1: KEUANGAN & TAGIHAN -->
            <div id="tab-content-finance" class="tab-pane space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Left Column: Active Invoice Summary -->
                    <div class="space-y-4">
                        <!-- Label-Caps Section Title (Design.md §6.9) -->
                        <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                RINGKASAN TAGIHAN AKTIF
                            </span>
                            <span id="hub-invoice-period-badge" class="px-2 py-0.5 bg-slate-100 text-slate-700 font-mono rounded text-[11px] font-semibold">-</span>
                        </div>

                        <!-- Info Rows with Dividers (Design.md §6.5) -->
                        <div class="divide-y divide-slate-100 text-xs">
                            <div class="flex justify-between py-2.5">
                                <span class="text-slate-500 font-medium">Paket Internet</span>
                                <span id="hub-fin-package" class="font-semibold text-slate-900 text-right">-</span>
                            </div>
                            <div class="flex justify-between py-2.5">
                                <span class="text-slate-500 font-medium">Harga Bulanan</span>
                                <span id="hub-fin-price" class="font-mono font-semibold text-slate-900">-</span>
                            </div>
                            <div class="flex justify-between py-2.5">
                                <span class="text-slate-500 font-medium">Jatuh Tempo</span>
                                <span id="hub-fin-due-date" class="font-mono font-medium text-slate-800">-</span>
                            </div>
                            <div class="flex justify-between py-2.5">
                                <span class="text-slate-500 font-medium">Total Piutang Lintas Periode</span>
                                <span id="hub-fin-arrears" class="font-mono font-bold text-rose-600">Rp 0</span>
                            </div>
                            <div class="flex justify-between py-2.5">
                                <span class="text-slate-500 font-medium">Diskon Tagihan</span>
                                <span id="hub-fin-discount" class="font-mono font-semibold text-emerald-600">Rp 0</span>
                            </div>
                        </div>

                        <!-- Highlighted Total Pay Row -->
                        <div class="pt-3 border-t border-slate-200 flex items-baseline justify-between">
                            <span class="text-xs font-bold text-slate-700">Total Harus Dibayar:</span>
                            <span id="hub-fin-total-pay" class="font-mono text-xl font-extrabold text-slate-900 tabular-nums">Rp 0</span>
                        </div>
                    </div>

                    <!-- Right Column: Quick Payment Form -->
                    <div class="space-y-4" id="payment-form-container">
                        <!-- Label-Caps Section Title (Design.md §6.9) -->
                        <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase flex items-center justify-between">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                INPUT PEMBAYARAN CASIER
                            </span>
                            <span class="text-[10px] text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100">Kasir Hub</span>
                        </div>

                        <form id="payment-form" method="POST" action="">
                            @csrf
                            <div class="space-y-3 text-xs">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-medium text-slate-600 mb-1">Tanggal Bayar *</label>
                                        <input type="date" name="payment_date" id="payment_date" value="{{ date('Y-m-d') }}" class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" required>
                                    </div>
                                    <div>
                                        <label class="block font-medium text-slate-600 mb-1">Metode *</label>
                                        <select name="payment_method" class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" required>
                                            <option value="cash">Tunai (Cash)</option>
                                            <option value="transfer">Transfer Bank</option>
                                            <option value="qris">QRIS</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block font-medium text-slate-600 mb-1">Nominal Diterima *</label>
                                        <input type="number" name="amount" id="payment_amount" class="w-full text-xs font-mono font-bold px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20" required>
                                    </div>
                                    <div>
                                        <label class="block font-medium text-slate-600 mb-1">Alokasi Tagihan</label>
                                        <select id="payment_allocation" class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                            <option value="Untuk Tagihan Bulanan">Tagihan Bulanan</option>
                                            <option value="Bayar Piutang">Bayar Piutang</option>
                                            <option value="Lebih Bayar">Lebih Bayar</option>
                                        </select>
                                    </div>
                                </div>

                                <div>
                                    <label class="block font-medium text-slate-600 mb-1">Keterangan / Catatan</label>
                                    <input type="text" name="note" id="payment_note" placeholder="Catatan pembayaran opsional..." class="w-full text-xs px-3 py-2 border border-slate-200 rounded-lg bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold py-2.5 px-4 rounded-lg shadow-2xs transition-colors cursor-pointer flex items-center justify-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    <span>Konfirmasi Pembayaran</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- No Invoice State -->
                    <div id="no-invoice-state" class="p-8 text-center hidden md:col-span-1 border border-dashed border-slate-200 rounded-xl bg-slate-50/50">
                        <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto mb-2">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <p class="text-xs font-bold text-slate-900">Tidak ada tagihan aktif atau piutang</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Semua kewajiban tagihan telah lunas.</p>
                    </div>
                </div>

                <!-- Recent Payments History Table (Design.md §6.4 & §6.5) -->
                <div class="pt-4 border-t border-slate-200 space-y-3">
                    <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase flex items-center justify-between">
                        <span>RIWAYAT 3 PEMBAYARAN TERAKHIR</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead>
                                <tr class="bg-slate-50 text-slate-500 font-semibold border-b border-slate-200">
                                    <th class="py-2.5 px-3">TANGGAL</th>
                                    <th class="py-2.5 px-3">NO. INVOICE</th>
                                    <th class="py-2.5 px-3">METODE</th>
                                    <th class="py-2.5 px-3 text-right">NOMINAL</th>
                                </tr>
                            </thead>
                            <tbody id="hub-recent-payments-body" class="divide-y divide-slate-100 font-sans">
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-slate-400">Belum ada riwayat pembayaran.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 2: TEKNIS & PERANGKAT -->
            <div id="tab-content-technical" class="tab-pane hidden space-y-6">
                <!-- Label-Caps Section Title -->
                <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase flex items-center justify-between">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"/></svg>
                        PARAMETRIK KONEKSI & PERANGKAT
                    </span>
                    <button onclick="copyTechInfo()" type="button" class="text-sky-600 hover:underline cursor-pointer text-xs font-semibold">Copy Semua Teknis &rarr;</button>
                </div>

                <!-- Info Rows with Dividers (Clean Canvas Style - Design.md §6.5) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1 text-xs divide-y sm:divide-y-0 divide-slate-100">
                    <div class="space-y-1">
                        <div class="flex justify-between py-2.5 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Username PPPoE</span>
                            <span id="hub-tech-pppoe" class="font-mono font-bold text-slate-900">-</span>
                        </div>
                        <div class="flex justify-between py-2.5 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">IP Address</span>
                            <span id="hub-tech-ip" class="font-mono font-bold text-sky-600">-</span>
                        </div>
                        <div class="flex justify-between py-2.5 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">SN / MAC ONU</span>
                            <span id="hub-tech-onu" class="font-mono font-semibold text-slate-800">-</span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div class="flex justify-between py-2.5 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">SN Router WiFi</span>
                            <span id="hub-tech-router" class="font-mono font-semibold text-slate-800">-</span>
                        </div>
                        <div class="flex justify-between py-2.5 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">ODP / Distribusi</span>
                            <span id="hub-tech-distribution" class="font-semibold text-slate-900">-</span>
                        </div>
                        <div class="flex justify-between py-2.5 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Skema Kontrak</span>
                            <span id="hub-tech-contract" class="font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded text-[11px] border border-slate-200">-</span>
                        </div>
                    </div>
                </div>

                <!-- Connection Control Box (Design.md Action Box) -->
                <div class="pt-4 border-t border-slate-200 flex items-center justify-between gap-4">
                    <div>
                        <span class="text-xs font-bold text-slate-900 block">Kontrol Status Koneksi Pelanggan</span>
                        <span class="text-[11px] text-slate-500">Ubah status koneksi internet pelanggan (Aktif / Isolir Suspend).</span>
                    </div>
                    <button id="btn-hub-toggle-connection" onclick="triggerHubToggleConnection()" type="button" class="bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-2xs transition-colors cursor-pointer shrink-0">
                        Toggle Koneksi
                    </button>
                </div>
            </div>

            <!-- TAB 3: LOKASI & LAPANGAN -->
            <div id="tab-content-field" class="tab-pane hidden space-y-6">
                <!-- Label-Caps Section Title -->
                <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase flex items-center justify-between">
                    <span class="inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        DATA ALAMAT & NAVIGASI TEKNISI
                    </span>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="flex flex-col gap-1 py-2 border-b border-slate-100">
                        <span class="text-slate-500 font-medium">Alamat Pemasangan Lengkap</span>
                        <p id="hub-field-address-full" class="text-sm font-semibold text-slate-900 leading-relaxed pt-0.5">-</p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-2">
                        <div class="py-2 border-b border-slate-100">
                            <span class="text-slate-500 font-medium block">Desa / Kelurahan</span>
                            <span id="hub-field-village" class="font-semibold text-slate-900 mt-1 block">-</span>
                        </div>
                        <div class="py-2 border-b border-slate-100">
                            <span class="text-slate-500 font-medium block">Kecamatan</span>
                            <span id="hub-field-district" class="font-semibold text-slate-900 mt-0.5 block">-</span>
                        </div>
                        <div class="py-2 border-b border-slate-100">
                            <span class="text-slate-500 font-medium block">Kota / Kabupaten</span>
                            <span id="hub-field-city" class="font-semibold text-slate-900 mt-0.5 block">-</span>
                        </div>
                        <div class="py-2 border-b border-slate-100">
                            <span class="text-slate-500 font-medium block">Kode Pos</span>
                            <span id="hub-field-postal-code" class="font-mono font-semibold text-slate-800 mt-0.5 block">-</span>
                        </div>
                    </div>
                </div>

                <!-- Section 3: GPS & Navigasi Direct -->
                <div class="space-y-3 pt-2">
                    <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            GEOLOKASI GPS & NAVIGASI LAPANGAN
                        </span>
                    </div>

                    <div class="flex justify-between items-center py-2.5 border-b border-slate-100 text-xs">
                        <span class="text-slate-500 font-medium">Koordinat GPS (Lat, Lng)</span>
                        <span id="hub-field-coords" class="font-mono font-bold text-slate-900 text-sm">-</span>
                    </div>
                </div>

                <!-- Field Direct Maps Launcher Card -->
                <div class="pt-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div>
                        <span class="text-xs font-bold text-slate-900 block">Navigasi Langsung Google Maps</span>
                        <span class="text-[11px] text-slate-500">Buka peta rute penanganan untuk teknisi lapangan secara instan.</span>
                    </div>
                    <a id="btn-field-launch-maps" href="#" target="_blank" class="w-full sm:w-auto bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold px-4 py-2 rounded-lg shadow-2xs transition-colors text-center shrink-0">
                        Buka Google Maps &rarr;
                    </a>
                </div>
            </div>

            <!-- TAB 4: PROFIL & ADMINISTRASI (Rich CS & Admin View) -->
            <div id="tab-content-profile" class="tab-pane hidden space-y-6">
                <!-- Section 1: Identitas Master -->
                <div class="space-y-3">
                    <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            IDENTITAS MASTER PELANGGAN
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1 text-xs divide-y sm:divide-y-0 divide-slate-100">
                        <div class="space-y-1">
                            <div class="flex justify-between py-2.5 border-b border-slate-100">
                                <span class="text-slate-500 font-medium">Nama Lengkap</span>
                                <span id="hub-prof-fullname" class="font-semibold text-slate-900">-</span>
                            </div>
                            <div class="flex justify-between py-2.5 border-b border-slate-100">
                                <span class="text-slate-500 font-medium">NIK / No. KTP</span>
                                <span id="hub-prof-nik" class="font-mono font-semibold text-slate-900">-</span>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <div class="flex justify-between py-2.5 border-b border-slate-100">
                                <span class="text-slate-500 font-medium">Kode Pelanggan (CID)</span>
                                <span id="hub-prof-cid" class="font-mono font-bold text-sky-600">-</span>
                            </div>
                            <div class="flex justify-between py-2.5 border-b border-slate-100">
                                <span class="text-slate-500 font-medium">Tanggal Registrasi</span>
                                <span id="hub-prof-reg" class="font-mono font-semibold text-slate-900">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Kontak Komunikasi -->
                <div class="space-y-3 pt-2">
                    <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            KONTAK & SALURAN KOMUNIKASI
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1 text-xs divide-y sm:divide-y-0 divide-slate-100">
                        <div class="flex justify-between py-2.5 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Telepon / WhatsApp</span>
                            <span id="hub-prof-phone" class="font-mono font-bold text-slate-900">-</span>
                        </div>
                        <div class="flex justify-between py-2.5 border-b border-slate-100">
                            <span class="text-slate-500 font-medium">Email Pelanggan</span>
                            <span id="hub-prof-email" class="font-medium text-slate-900 truncate max-w-[160px]">-</span>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Status Kelengkapan Data -->
                <div class="space-y-3 pt-2">
                    <div class="text-[10px] font-semibold tracking-wider text-slate-500 uppercase flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            STATUS KELENGKAPAN ADMINISTRASI
                        </span>
                        <span id="hub-prof-completeness-status" class="font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 text-[11px]">-</span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                        <div class="flex justify-between items-center text-xs">
                            <span class="font-medium text-slate-700">Kemajuan Kelengkapan Berkas:</span>
                            <span id="hub-prof-completeness-bar-text" class="font-mono font-bold text-sky-600">0%</span>
                        </div>
                        <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                            <div id="hub-prof-completeness-bar" class="h-full bg-sky-600 transition-all duration-300" style="width: 0%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Hub: Primary Action Footer Bar (Requirement User & Design.md) -->
        <div class="px-6 py-3.5 bg-slate-50 border-t border-slate-200 flex flex-wrap items-center justify-between gap-3 shrink-0">
            <!-- Left Group: Navigation & Management Actions -->
            <div class="flex flex-wrap items-center gap-2">
                <button onclick="triggerDetail()" type="button" class="bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold px-3.5 py-2 rounded-lg transition-colors cursor-pointer shadow-2xs flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    <span>Lihat Full Detail</span>
                </button>

                @if(auth()->user()->hasPermission('customers.update'))
                <button onclick="triggerEdit()" type="button" class="bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 text-xs font-semibold px-3.5 py-2 rounded-lg transition-colors cursor-pointer shadow-2xs flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Edit Master Data</span>
                </button>
                @endif
            </div>

            <!-- Right Group: Termination & Close Buttons -->
            <div class="flex flex-wrap items-center gap-2">
                @if(auth()->user()->hasPermission('customers.delete'))
                <button onclick="triggerTerminate()" type="button" class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold px-3.5 py-2 rounded-lg transition-colors cursor-pointer shadow-2xs flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    <span>Putus Langganan</span>
                </button>
                @endif

                <button onclick="closeActionsModal()" type="button" class="text-xs font-semibold text-slate-700 hover:text-slate-900 bg-white hover:bg-slate-100 border border-slate-200 px-4 py-2 rounded-lg transition-colors cursor-pointer shadow-2xs">
                    Tutup Modal
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Customer Detail Modal -->
<div id="detail-modal" class="fixed inset-0 bg-slate-900/60 flex items-center justify-center hidden z-50 p-4 transition-all duration-300">
    <div class="bg-white border border-slate-200 rounded-lg w-full max-w-lg shadow-xl overflow-hidden transform scale-95 transition-all duration-300">
        <!-- Header -->
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                <span>Detail Pelanggan</span>
                <span id="modal-code-badge" class="px-2 py-0.5 bg-slate-200 text-slate-700 rounded text-xs font-mono data-text"></span>
            </h3>
            <button onclick="closeDetailModal()" class="p-1 rounded hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition-colors cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <!-- Body -->
        <div class="p-6 space-y-4 text-sm text-slate-700">
            <!-- Row 1: Name -->
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">NAMA LENGKAP</span>
                <span id="modal-name" class="font-semibold text-slate-900 text-base"></span>
            </div>

            <!-- Row 2: Contact -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">TELEPON</span>
                    <span id="modal-phone" class="font-mono text-slate-900 data-text"></span>
                </div>
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">EMAIL</span>
                    <span id="modal-email" class="text-slate-900 truncate block"></span>
                </div>
            </div>

            <hr class="border-slate-100">

            <!-- Row 3: Service & Date -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">PAKET INTERNET</span>
                    <span id="modal-package" class="font-medium text-slate-900"></span>
                </div>
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">HARGA BULANAN</span>
                    <span id="modal-price" class="font-mono text-slate-900 data-text"></span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">TANGGAL DAFTAR</span>
                    <span id="modal-reg-date" class="font-mono text-slate-900 data-text"></span>
                </div>
                <div>
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">STATUS LAYANAN</span>
                    <div id="modal-status-container" class="mt-0.5"></div>
                </div>
            </div>

            <hr class="border-slate-100">

            <!-- Row 4: Address -->
            <div>
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">ALAMAT INSTALASI</span>
                <p id="modal-address" class="text-slate-800 leading-relaxed"></p>
                <div class="flex gap-1.5 mt-1">
                    <span id="modal-village" class="px-2 py-0.5 bg-slate-100 rounded text-xs text-slate-500"></span>
                    <span id="modal-district" class="px-2 py-0.5 bg-slate-100 rounded text-xs text-slate-500"></span>
                </div>
            </div>
        </div>
        <!-- Footer -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end gap-2">
            <button onclick="closeDetailModal()" class="btn-secondary">Tutup</button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    let selectedCustomerData = {};

    function switchActionTab(tabName) {
        const tabs = ['finance', 'technical', 'field', 'profile'];
        tabs.forEach(t => {
            const btn = document.getElementById(`tab-btn-${t}`);
            const content = document.getElementById(`tab-content-${t}`);
            if (t === tabName) {
                if (btn) btn.className = 'py-2.5 px-4 rounded-t-lg font-semibold flex items-center gap-2 border-b-2 border-sky-600 text-sky-600 bg-white transition-all cursor-pointer';
                if (content) content.classList.remove('hidden');
            } else {
                if (btn) btn.className = 'py-2.5 px-4 rounded-t-lg font-medium text-slate-500 hover:text-slate-900 transition-all cursor-pointer';
                if (content) content.classList.add('hidden');
            }
        });
    }

    function openActionsModal(button) {
        const modal = document.getElementById('actions-modal');
        if (!modal) return;
        const content = modal.querySelector('.transform');

        selectedCustomerData = {
            id: button.getAttribute('data-id'),
            code: button.getAttribute('data-code'),
            name: button.getAttribute('data-name'),
            nik: button.getAttribute('data-nik') || '-',
            phone: button.getAttribute('data-phone') || '',
            email: button.getAttribute('data-email') || '-',
            status: button.getAttribute('data-status') || '-',
            rawStatus: button.getAttribute('data-raw-status') || 'active',
            pop: button.getAttribute('data-pop') || '-',
            reg: button.getAttribute('data-reg') || '-',
            package: button.getAttribute('data-package') || '-',
            bandwidth: button.getAttribute('data-bandwidth') || '-',
            price: button.getAttribute('data-price') || '-',
            address: button.getAttribute('data-address') || '-',
            landmark: button.getAttribute('data-landmark') || '-',
            rtRw: button.getAttribute('data-rt-rw') || '-',
            village: button.getAttribute('data-village') || '-',
            district: button.getAttribute('data-district') || '-',
            city: button.getAttribute('data-city') || '-',
            postalCode: button.getAttribute('data-postal-code') || '-',
            lat: button.getAttribute('data-lat') || '',
            lng: button.getAttribute('data-lng') || '',
            completenessPct: button.getAttribute('data-completeness-pct') || '0',
            completenessStatus: button.getAttribute('data-completeness-status') || 'Draft',
            pppoe: button.getAttribute('data-pppoe') || '-',
            ip: button.getAttribute('data-ip') || '-',
            vlan: button.getAttribute('data-vlan') || '-',
            onu: button.getAttribute('data-onu') || '-',
            onuBrand: button.getAttribute('data-onu-brand') || '-',
            router: button.getAttribute('data-router') || '-',
            routerBrand: button.getAttribute('data-router-brand') || '-',
            contract: button.getAttribute('data-contract') || '-',
            distribution: button.getAttribute('data-distribution') || '-',
        };

        const setElemText = (id, txt) => {
            const el = document.getElementById(id);
            if (el) el.innerText = txt;
        };

        // 1. Header & Static Hub Bindings
        setElemText('actions-modal-title', selectedCustomerData.name);
        setElemText('actions-modal-code', selectedCustomerData.code);
        
        // Status Badge Style (Design.md §13.1)
        const badgeEl = document.getElementById('actions-modal-status-badge');
        if (badgeEl) {
            const statusLabelSpan = badgeEl.querySelector('span:last-child') || badgeEl;
            statusLabelSpan.innerText = selectedCustomerData.status.toUpperCase();
            if (selectedCustomerData.rawStatus === 'active') {
                badgeEl.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200';
            } else if (selectedCustomerData.rawStatus === 'suspended') {
                badgeEl.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border bg-purple-50 text-purple-700 border-purple-200';
            } else {
                badgeEl.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border bg-rose-50 text-rose-700 border-rose-200';
            }
        }

        const fullLoc = `Kel. ${selectedCustomerData.village}, Kec. ${selectedCustomerData.district}`;
        setElemText('actions-modal-location-text', fullLoc);
        setElemText('actions-modal-pop-text', selectedCustomerData.pop);
        setElemText('actions-modal-completeness-pct', selectedCustomerData.completenessPct + '%');

        // 2. Direct Links (WA & Google Maps)
        const waBtn = document.getElementById('btn-quick-wa');
        if (waBtn) {
            let cleanPhone = selectedCustomerData.phone.replace(/[^0-9]/g, '');
            if (cleanPhone.startsWith('0')) {
                cleanPhone = '62' + cleanPhone.substring(1);
            }
            waBtn.href = cleanPhone ? `https://wa.me/${cleanPhone}?text=Halo%20${encodeURIComponent(selectedCustomerData.name)},%20kami%20dari%20Whusnet%20Billing...` : '#';
        }

        const mapsBtn = document.getElementById('btn-quick-maps');
        const fieldMapsBtn = document.getElementById('btn-field-launch-maps');
        let mapsUrl = '#';
        if (selectedCustomerData.lat && selectedCustomerData.lng) {
            mapsUrl = `https://www.google.com/maps/search/?api=1&query=${selectedCustomerData.lat},${selectedCustomerData.lng}`;
        } else {
            const queryAddr = `${selectedCustomerData.address}, ${selectedCustomerData.village}, ${selectedCustomerData.district}`;
            mapsUrl = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(queryAddr)}`;
        }
        if (mapsBtn) mapsBtn.href = mapsUrl;
        if (fieldMapsBtn) fieldMapsBtn.href = mapsUrl;

        // 3. Tab Bindings (Pre-fill immediately)
        // Tab 1: Finance
        setElemText('hub-fin-package', selectedCustomerData.package);
        setElemText('hub-fin-price', selectedCustomerData.price);

        // Tab 2: Technical
        setElemText('hub-tech-pppoe', selectedCustomerData.pppoe);
        setElemText('hub-tech-ip', selectedCustomerData.ip);
        setElemText('hub-tech-vlan', selectedCustomerData.vlan);
        setElemText('hub-tech-bandwidth', selectedCustomerData.bandwidth);
        setElemText('hub-tech-pop', selectedCustomerData.pop);
        setElemText('hub-tech-distribution', selectedCustomerData.distribution);
        setElemText('hub-tech-onu', selectedCustomerData.onu);
        setElemText('hub-tech-onu-brand', selectedCustomerData.onuBrand);
        setElemText('hub-tech-router', selectedCustomerData.router);
        setElemText('hub-tech-contract', selectedCustomerData.contract);

        // Tab 3: Field Location
        setElemText('hub-field-address-full', `${selectedCustomerData.address !== '-' ? selectedCustomerData.address + ', ' : ''}Kel. ${selectedCustomerData.village}, Kec. ${selectedCustomerData.district}`);
        setElemText('hub-field-landmark', selectedCustomerData.landmark);
        setElemText('hub-field-rt-rw', selectedCustomerData.rtRw);
        setElemText('hub-field-village', selectedCustomerData.village);
        setElemText('hub-field-district', selectedCustomerData.district);
        setElemText('hub-field-city', selectedCustomerData.city);
        setElemText('hub-field-postal-code', selectedCustomerData.postalCode);
        setElemText('hub-field-coords', (selectedCustomerData.lat && selectedCustomerData.lng) ? `${selectedCustomerData.lat}, ${selectedCustomerData.lng}` : 'Belum Diatur');

        // Tab 4: Profile & Administration
        setElemText('hub-prof-fullname', selectedCustomerData.name);
        setElemText('hub-prof-nik', selectedCustomerData.nik);
        setElemText('hub-prof-cid', selectedCustomerData.code);
        setElemText('hub-prof-phone', selectedCustomerData.phone || '-');
        setElemText('hub-prof-email', selectedCustomerData.email || '-');
        setElemText('hub-prof-reg', selectedCustomerData.reg);
        setElemText('hub-prof-completeness-status', selectedCustomerData.completenessStatus);
        setElemText('hub-prof-completeness-bar-text', selectedCustomerData.completenessPct + '%');
        const compBar = document.getElementById('hub-prof-completeness-bar');
        if (compBar) compBar.style.width = selectedCustomerData.completenessPct + '%';

        // Default to Tab 1
        switchActionTab('finance');

        // Show Modal with Animation
        modal.classList.remove('hidden');
        if (content) {
            setTimeout(() => {
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 10);
        }

        // 4. Fetch Live Payment & Hub Info concurrently
        const loadingEl = document.getElementById('modal-hub-loading');
        if (loadingEl) loadingEl.classList.remove('hidden');

        fetch(`/customers/${selectedCustomerData.id}/payment-info`)
            .then(res => res.json())
            .then(data => {
                if (loadingEl) loadingEl.classList.add('hidden');
                const formatRp = (num) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);

                const payFormContainer = document.getElementById('payment-form-container');
                const noInvoiceState = document.getElementById('no-invoice-state');

                if (data.invoice_id) {
                    if (payFormContainer) payFormContainer.classList.remove('hidden');
                    if (noInvoiceState) noInvoiceState.classList.add('hidden');

                    setElemText('hub-invoice-period-badge', `Periode: ${data.billing_period || '-'}`);
                    setElemText('hub-fin-due-date', data.due_date || '-');
                    setElemText('hub-fin-arrears', data.total_piutang > 0 ? formatRp(data.total_piutang) : 'Rp 0');
                    setElemText('hub-fin-discount', data.discount > 0 ? formatRp(data.discount) : 'Rp 0');
                    setElemText('hub-fin-total-pay', formatRp(data.remaining_amount));

                    // Payment Form Setup
                    const payForm = document.getElementById('payment-form');
                    if (payForm) payForm.action = `/invoices/${data.invoice_id}/payments`;
                    
                    const amountInput = document.getElementById('payment_amount');
                    if (amountInput) {
                        amountInput.value = data.remaining_amount;
                        amountInput.max = data.remaining_amount;
                    }

                    const allocSelect = document.getElementById('payment_allocation');
                    const noteInput = document.getElementById('payment_note');
                    if (noteInput && allocSelect) {
                        noteInput.value = 'Pembayaran: ' + allocSelect.value;
                    }
                } else {
                    if (payFormContainer) payFormContainer.classList.add('hidden');
                    if (noInvoiceState) noInvoiceState.classList.remove('hidden');

                    setElemText('hub-invoice-period-badge', 'Tidak Ada Tagihan Aktif');
                    setElemText('hub-fin-due-date', '-');
                    setElemText('hub-fin-arrears', data.total_piutang > 0 ? formatRp(data.total_piutang) : 'Rp 0');
                    setElemText('hub-fin-discount', 'Rp 0');
                    setElemText('hub-fin-total-pay', 'Rp 0');
                }

                // Render 3 Recent Payments
                const tbody = document.getElementById('hub-recent-payments-body');
                if (tbody) {
                    if (data.recent_payments && data.recent_payments.length > 0) {
                        tbody.innerHTML = data.recent_payments.map(p => `
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="py-2.5 px-3 font-mono text-slate-600">${p.date}</td>
                                <td class="py-2.5 px-3 font-mono font-semibold text-slate-800">${p.invoice_number}</td>
                                <td class="py-2.5 px-3"><span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-[10px] font-semibold border border-slate-200">${p.method}</span></td>
                                <td class="py-2.5 px-3 text-right font-mono font-bold text-emerald-600">${formatRp(p.amount)}</td>
                            </tr>
                        `).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-slate-400">Belum ada riwayat pembayaran.</td></tr>';
                    }
                }

                // Live overrides if backend technical info returned
                if (data.technical) {
                    setElemText('hub-tech-pppoe', data.technical.pppoe_username || selectedCustomerData.pppoe);
                    setElemText('hub-tech-ip', data.technical.ip_address || selectedCustomerData.ip);
                    setElemText('hub-tech-onu', data.technical.onu_sn || selectedCustomerData.onu);
                    setElemText('hub-tech-router', data.technical.router_sn || selectedCustomerData.router);
                    setElemText('hub-tech-distribution', data.technical.distribution || selectedCustomerData.distribution);
                }
            })
            .catch(err => {
                console.error(err);
                if (loadingEl) loadingEl.classList.add('hidden');
            });
    }

    function copyTechInfo() {
        const textToCopy = `[DATA TEKNIS PELANGGAN]
Nama: ${selectedCustomerData.name} (${selectedCustomerData.code})
POP: ${selectedCustomerData.pop}
PPPoE: ${selectedCustomerData.pppoe}
IP: ${selectedCustomerData.ip}
ONU SN: ${selectedCustomerData.onu}
Router SN: ${selectedCustomerData.router}
ODP/Distribusi: ${selectedCustomerData.distribution}`;

        navigator.clipboard.writeText(textToCopy).then(() => {
            if (window.Toast) {
                window.Toast.success('Berhasil Disalin', 'Kredensial teknis telah disalin ke clipboard.');
            } else {
                alert('Kredensial teknis telah disalin!');
            }
        });
    }

    function triggerHubToggleConnection() {
        const isCurrentActive = selectedCustomerData.rawStatus === 'active';
        const actionText = isCurrentActive ? 'mengisolir / menonaktifkan' : 'mengaktifkan kembali';

        if (window.Confirm) {
            window.Confirm(
                'Konfirmasi Status Layanan',
                `Apakah Anda yakin ingin ${actionText} koneksi internet untuk pelanggan ${selectedCustomerData.name}?`,
                'warning',
                () => {
                    if (window.Toast) {
                        window.Toast.success('Status Diubah', `Anda berhasil ${actionText} koneksi internet untuk ${selectedCustomerData.name}.`);
                    }
                }
            );
        } else {
            if (confirm(`Apakah Anda yakin ingin ${actionText} koneksi internet untuk ${selectedCustomerData.name}?`)) {
                alert(`Status koneksi ${selectedCustomerData.name} diubah.`);
            }
        }
    }

    // Event listener for allocation change to update note
    document.addEventListener('change', function(e) {
        if (e.target && e.target.id === 'payment_allocation') {
            const noteInput = document.getElementById('payment_note');
            const currentNote = noteInput.value.replace(/Pembayaran: [^,]+(,\s*)?/, '');
            noteInput.value = 'Pembayaran: ' + e.target.value + (currentNote ? ', ' + currentNote : '');
        }
    });

    function closeActionsModal() {
        const modal = document.getElementById('actions-modal');
        const content = modal.querySelector('.transform');
        content.classList.remove('scale-100');
        content.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 150);
    }

    function triggerDetail() {
        window.location.href = '/customers/' + selectedCustomerData.id;
    }

    function triggerEdit() {
        window.location.href = '/customers/' + selectedCustomerData.id + '/edit';
    }

    function triggerTerminate() {
        closeActionsModal();
        if (window.Dialog) {
            window.Dialog.show({
                title: 'Konfirmasi Terminasi',
                message: `Apakah Anda yakin ingin melakukan TERMINASI / PEMUTUSAN kontrak layanan untuk ${selectedCustomerData.name} (${selectedCustomerData.code})?`,
                icon: 'error',
                buttons: [
                    { text: 'Batal', type: 'secondary' },
                    { text: 'Ya, Terminasi', type: 'danger', onClick: () => {
                        window.Dialog.close();
                        if (window.Toast) window.Toast.info('Terminasi', `Layanan untuk ${selectedCustomerData.name} telah masuk daftar terminasi.`);
                    }}
                ]
            });
        } else if (confirm(`Apakah Anda yakin ingin melakukan TERMINASI untuk ${selectedCustomerData.name}?`)) {
            alert(`Layanan untuk ${selectedCustomerData.name} telah masuk daftar terminasi.`);
        }
    }

    function toggleConnection(id, name, checkbox) {
        const isChecked = checkbox.checked;
        const actionText = isChecked ? 'mengaktifkan kembali' : 'mengisolir / menonaktifkan';
        
        if (window.Confirm) {
            window.Confirm(
                'Konfirmasi Perubahan Status',
                `Apakah Anda yakin ingin ${actionText} koneksi internet untuk pelanggan ${name}?`,
                'warning',
                () => {
                    if (window.Toast) window.Toast.success('Koneksi Diubah', `Anda berhasil ${actionText} koneksi internet untuk pelanggan ${name}.`);
                },
                () => {
                    checkbox.checked = !isChecked;
                }
            );
        }
    }

    function openDetailModal(button) {
        openActionsModal(button);
    }

    function closeDetailModal() {
        const modal = document.getElementById('detail-modal');
        const content = modal.querySelector('.transform');
        if (content) {
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
        }
        setTimeout(() => {
            if (modal) modal.classList.add('hidden');
        }, 150);
    }

    // ══ Modal Atur Mini POP & Distribusi (dipakai bareng semua row) ══════
    function openNetworkAssignmentModal(customerId) {
        const form = document.getElementById('network-assignment-form');
        const miniPopSelect = document.getElementById('na-mini-pop-select');
        const distSelect = document.getElementById('na-distribution-select');
        const warning = document.getElementById('na-blocked-warning');
        const submitBtn = document.getElementById('na-submit-btn');

        const custNameEl = document.getElementById('na-customer-name');
        const custCidEl = document.getElementById('na-customer-cid');
        const popNameEl = document.getElementById('na-pop-name');

        form.action = `/customers/${customerId}/network-assignment`;
        miniPopSelect.innerHTML = '<option value="">Memuat...</option>';
        distSelect.innerHTML = '<option value="">—</option>';
        custNameEl.textContent = 'Memuat...';
        custCidEl.textContent = '—';
        popNameEl.textContent = '—';
        warning.classList.add('hidden');
        submitBtn.disabled = true;

        fetch(`/customers/${customerId}/network-assignment`, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => {
                custNameEl.textContent = data.customer_name;
                custCidEl.textContent = data.customer_cid || 'DRAFT';
                popNameEl.textContent = `${data.pop_name} (${data.pop_code})`;

                miniPopSelect.innerHTML = '<option value="">— Belum di-assign —</option>';
                data.mini_pops.forEach(mp => {
                    const opt = document.createElement('option');
                    opt.value = mp.id;
                    opt.textContent = `[${mp.pop_code}] ${mp.name}`;
                    if (data.current.mini_pop_id === mp.id) opt.selected = true;
                    miniPopSelect.appendChild(opt);
                });

                distSelect.dataset.allOptions = JSON.stringify(data.distributions);
                distSelect.dataset.currentDistributionId = data.current.distribution_id ?? '';
                renderDistributionOptions();

                if (!data.editable) {
                    warning.classList.remove('hidden');
                    submitBtn.disabled = true;
                } else {
                    submitBtn.disabled = false;
                }
            })
            .catch(() => {
                custNameEl.textContent = 'Gagal memuat data. Coba lagi.';
            });

        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'network-assignment-list' }));
    }

    function renderDistributionOptions() {
        const miniPopSelect = document.getElementById('na-mini-pop-select');
        const distSelect = document.getElementById('na-distribution-select');
        const selectedMiniPopId = miniPopSelect.value;
        const currentDistributionId = distSelect.dataset.currentDistributionId || '';
        const allDistributions = JSON.parse(distSelect.dataset.allOptions || '[]');

        distSelect.innerHTML = '<option value="">— Belum di-assign —</option>';
        allDistributions
            .filter(d => String(d.pop_id) === String(selectedMiniPopId))
            .forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.textContent = d.name ? `[${d.code}] ${d.name}` : d.code;
                if (String(currentDistributionId) === String(d.id)) opt.selected = true;
                distSelect.appendChild(opt);
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const miniPopSelect = document.getElementById('na-mini-pop-select');
        if (miniPopSelect) {
            miniPopSelect.addEventListener('change', renderDistributionOptions);
        }
    });
</script>

@if(auth()->user()->hasPermission('customers.detail.installation.validate'))
<x-ui.modal name="network-assignment-list" title="Atur Mini POP & Distribusi" maxWidth="sm">
    {{-- Header Info Pelanggan (Naked/Borderless Style) --}}
    <div class="pb-3 mb-4 border-b border-border space-y-1">
        <div class="flex justify-between items-start">
            <div>
                <span class="text-[10px] font-bold text-text-muted uppercase tracking-wider block">Pelanggan</span>
                <span id="na-customer-name" class="text-sm font-bold text-text-main">Memuat...</span>
            </div>
            <div class="text-right">
                <span class="text-[10px] font-bold text-text-muted uppercase tracking-wider block">ID Jaringan</span>
                <span id="na-customer-cid" class="font-mono text-xs text-text-main bg-surface-muted px-1.5 py-0.5 rounded border border-border">—</span>
            </div>
        </div>
        <div class="text-xs text-text-muted pt-1 flex items-center gap-1">
            <svg class="w-3.5 h-3.5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
            <span>Cabang: <strong id="na-pop-name" class="text-text-secondary font-semibold">—</strong></span>
        </div>
    </div>

    {{-- Info Alert Banner --}}
    <div class="flex gap-2 text-xs text-text-muted leading-relaxed bg-primary-soft/30 p-2.5 rounded-md border border-primary-border/40 mb-4">
        <svg class="w-4 h-4 text-primary shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span>Mini POP (OLT) & Distribusi dapat diatur setelah pemasangan dimulai, dan disesuaikan dengan konfigurasi MikroTik aktual.</span>
    </div>

    {{-- Blocked Warning Banner --}}
    <div id="na-blocked-warning" class="hidden flex gap-2 text-xs text-error leading-relaxed bg-error-bg/30 p-2.5 rounded-md border border-error-border/40 mb-4">
        <svg class="w-4 h-4 text-error shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
        <span>Mini POP & Distribusi cuma bisa diatur setelah proses pemasangan dimulai.</span>
    </div>

    <form id="network-assignment-form" method="POST" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted">Mini POP (OLT)</label>
            <select id="na-mini-pop-select" name="mini_pop_id" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-md bg-white font-ui focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all duration-200 cursor-pointer">
                <option value="">Memuat...</option>
            </select>
        </div>

        <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wider text-text-muted">Distribusi</label>
            <select id="na-distribution-select" name="distribution_id" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-md bg-white font-ui focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all duration-200 cursor-pointer">
                <option value="">—</option>
            </select>
            <p class="text-[11px] text-text-muted">Daftar Distribusi otomatis mengikuti Mini POP yang dipilih di atas.</p>
        </div>

        <div class="flex justify-end gap-2 pt-2 border-t border-border mt-4">
            <button type="button" onclick="window.dispatchEvent(new CustomEvent('close-modal', { detail: 'network-assignment-list' }))"
                    class="text-sm font-semibold px-4 py-2 rounded-md border border-slate-200 bg-white hover:bg-slate-50 text-text-secondary transition-colors duration-200 cursor-pointer">Batal</button>
            <button type="submit" id="na-submit-btn" class="text-sm font-semibold px-5 py-2 rounded-md text-white bg-primary hover:bg-primary-hover shadow-sm transition-colors duration-200 cursor-pointer">Simpan</button>
        </div>
    </form>
</x-ui.modal>
@endif
@endsection
