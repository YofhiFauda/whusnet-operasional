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
<!-- Top Action Bar -->
<div class="flex justify-between items-center mb-6">
    <h3 class="text-slate-800 text-sm font-semibold uppercase tracking-wider">Kelola Daftar Pelanggan</h3>
    <div class="flex gap-2">
        <a href="/customers/import" class="bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold py-2.5 px-4 rounded-md transition-colors inline-flex items-center gap-2 shadow-sm focus:outline-none focus:ring-2 focus:ring-slate-500/25 cursor-pointer">
            <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>Import Pelanggan</span>
        </a>
        <a href="/customers/create" class="bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold py-2.5 px-4 rounded-md transition-colors inline-flex items-center gap-2 shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500/25 cursor-pointer">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>Tambah Pelanggan</span>
        </a>
    </div>
</div>

<!-- Filter & Search Panel -->
<div class="bg-white border border-slate-200 rounded-lg p-6 mb-6">
    <form action="/customers" method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 items-end">
        <!-- Search -->
        <div>
            <label for="search" class="block text-xs font-semibold text-slate-500 mb-2">CARI PELANGGAN</label>
            <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Cari nama, No. HP, atau ID Lama..." class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
        </div>

        <!-- POP Filter -->
        <div>
            <label for="pop_id" class="block text-xs font-semibold text-slate-500 mb-2">POP / CABANG</label>
            <select name="pop_id" id="pop_id" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua POP</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" {{ $popId == $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <!-- Kecamatan Filter -->
        <div>
            <label for="district_id" class="block text-xs font-semibold text-slate-500 mb-2">KECAMATAN</label>
            <select name="district_id" id="district_id" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua Kecamatan</option>
                @foreach($districts as $district)
                    <option value="{{ $district->id }}" {{ $districtId == $district->id ? 'selected' : '' }}>{{ $district->name }}</option>
                @endforeach
            </select>
        </div>

        <!-- Paket Layanan Filter -->
        <div>
            <label for="package_id" class="block text-xs font-semibold text-slate-500 mb-2">PAKET LAYANAN</label>
            <select name="package_id" id="package_id" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua Paket</option>
                @foreach($packages as $package)
                    <option value="{{ $package->id }}" {{ $packageId == $package->id ? 'selected' : '' }}>{{ $package->package_code }} - {{ $package->name }}</option>
                @endforeach
            </select>
        </div>

        <!-- Status Kelengkapan Filter -->
        <div>
            <label for="completeness_status" class="block text-xs font-semibold text-slate-500 mb-2">STATUS KELENGKAPAN</label>
            <select name="completeness_status" id="completeness_status" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                <option value="">Semua Kelengkapan</option>
                <option value="draft" {{ $completenessStatus === 'draft' ? 'selected' : '' }}>Draft</option>
                <option value="perlu_dilengkapi" {{ $completenessStatus === 'perlu_dilengkapi' ? 'selected' : '' }}>Perlu Dilengkapi</option>
                <option value="lengkap" {{ $completenessStatus === 'lengkap' ? 'selected' : '' }}>Lengkap</option>
                <option value="siap_billing" {{ $completenessStatus === 'siap_billing' ? 'selected' : '' }}>Siap Billing</option>
            </select>
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2">
            <button type="submit" class="flex-1 bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500/25">
                Cari
            </button>
            <a href="/customers" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none">
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
                Semua <span class="ml-1 px-1.5 py-0.25 bg-white/25 text-white rounded-full text-[10px] data-text">{{ $totalCustomers }}</span>
            </a>
            @foreach($subscriptionStatuses as $subscriptionStatus)
                <a href="{{ request()->fullUrlWithQuery(['status' => $subscriptionStatus->code]) }}" class="px-3 py-1.5 rounded-md text-xs font-medium cursor-pointer transition-colors {{ $status === $subscriptionStatus->code ? 'bg-sky-600 text-white' : 'text-slate-600 hover:bg-slate-200/50' }}">
                    {{ $subscriptionStatus->name }} <span class="ml-1 px-1.5 py-0.25 bg-white/25 rounded-full text-[10px] data-text">{{ $statusCounts[$subscriptionStatus->code] ?? 0 }}</span>
                </a>
            @endforeach
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
            <a href="/customers" class="text-xs font-medium text-sky-600 hover:text-sky-800 hover:underline">(Kembali ke Semua Pelanggan)</a>
        </div>
    </div>
    @endif

    <!-- Table Container -->
    <div class="overflow-x-auto">
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
                        {{ $displayId }}
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
                                data-phone="{{ $customer->primary_phone ?? $customer->phone }}"
                                data-email="{{ $customer->email }}"
                                data-status="{{ $customer->subscriptionStatus->name ?? Str::headline($customer->status) }}"
                                data-reg="{{ \App\Support\IndonesianDate::date($customer->registration_date) }}"
                                data-package="{{ $customer->internetPackage ? $customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name : '-' }}"
                                data-price="{{ $customer->internetPackage ? 'Rp ' . number_format($customer->internetPackage->monthly_price, 0, ',', '.') : '-' }}"
                                data-address="{{ $customer->address }}"
                                data-village="{{ $customer->village->name ?? ($customer->customerAddress->village ?? '-') }}"
                                data-district="{{ $customer->district->name ?? ($customer->customerAddress->district ?? '-') }}"
                                class="inline-flex items-center text-xs font-medium text-sky-600 hover:text-sky-800 transition-colors border border-sky-200 hover:bg-sky-50 rounded px-2.5 py-1 cursor-pointer">
                            Action
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

<!-- Customer Action & Payment Modal -->
<div id="actions-modal" class="fixed inset-0 bg-slate-900/60 flex items-center justify-center hidden z-40 p-4 transition-all duration-300 overflow-y-auto">
    <div class="bg-white border border-slate-200 rounded-lg w-full max-w-2xl shadow-xl overflow-hidden transform scale-95 transition-all duration-300 my-8">
        <!-- Header -->
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-slate-800" id="actions-modal-title">Nama Pelanggan</h3>
                <p class="text-xs font-mono text-slate-400 mt-0.5 data-text" id="actions-modal-code">ID-0000</p>
            </div>
            <button onclick="closeActionsModal()" class="p-1 rounded hover:bg-slate-200 text-slate-400 hover:text-slate-600 transition-colors cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        
        <!-- Loading State -->
        <div id="modal-loading" class="p-8 text-center hidden">
            <svg class="animate-spin h-6 w-6 text-sky-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <p class="text-xs text-slate-500 mt-2">Memuat data tagihan...</p>
        </div>

        <!-- Body Content -->
        <div id="modal-content" class="hidden">
            <!-- Informasi Layanan & Tagihan -->
            <div class="p-6 bg-white border-b border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-slate-700">
                <div class="space-y-3">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Informasi Layanan</span>
                    <div>
                        <span class="text-xs font-semibold text-slate-500 block">Alamat</span>
                        <span id="modal-info-address" class="font-medium text-slate-900"></span>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-500 block">Paket Internet</span>
                        <span id="modal-info-package" class="font-medium text-slate-900"></span>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-500 block">Harga Bulanan</span>
                        <span id="modal-info-price" class="font-mono text-slate-900"></span>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Informasi Tagihan Aktif</span>
                    <div>
                        <span class="text-xs font-semibold text-slate-500 block">Periode & Jatuh Tempo</span>
                        <span id="modal-info-period" class="font-medium text-slate-900"></span>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-500 block">Status Piutang</span>
                        <span id="modal-info-arrears" class="font-mono font-bold text-red-600"></span>
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-500 block">Diskon</span>
                        <span id="modal-info-discount" class="font-mono text-emerald-600"></span>
                    </div>
                    <div class="pt-2 mt-2 border-t border-slate-100">
                        <span class="text-xs font-semibold text-slate-500 block">Total Sisa Tagihan (Yg Harus Dibayar)</span>
                        <span id="modal-info-total" class="font-mono text-lg font-bold text-slate-900"></span>
                    </div>
                </div>
            </div>

            <!-- Form Pembayaran -->
            <div class="p-6 bg-slate-50/50" id="payment-form-container">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">Input Pembayaran</span>
                <form id="payment-form" method="POST" action="">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Tanggal Pembayaran</label>
                            <input type="date" name="payment_date" id="payment_date" value="{{ date('Y-m-d') }}" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Metode</label>
                            <select name="payment_method" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" required>
                                <option value="cash">Tunai (Cash)</option>
                                <option value="transfer">Transfer Bank</option>
                                <option value="qris">QRIS</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Nominal Pembayaran</label>
                            <input type="number" name="amount" id="payment_amount" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25" required>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Alokasi Pembayaran</label>
                            <select id="payment_allocation" class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                                <option value="Untuk Tagihan Bulanan">Tagihan Bulanan</option>
                                <option value="Bayar Piutang">Bayar Piutang</option>
                                <option value="Lebih Bayar">Lebih Bayar</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-slate-500 mb-1">Keterangan Tambahan</label>
                            <input type="text" name="note" id="payment_note" placeholder="Keterangan opsional..." class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold py-2 px-5 rounded-md shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-emerald-500/25 cursor-pointer">
                            Simpan Pembayaran
                        </button>
                    </div>
                </form>
            </div>
            
            <div id="no-invoice-state" class="p-8 text-center hidden">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 mb-3">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-900">Tidak ada tagihan aktif atau piutang.</p>
                <p class="text-xs text-slate-500 mt-1">Pelanggan ini sudah melunasi semua tagihannya.</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
            <div class="flex gap-2">
                <button onclick="triggerDetail()" class="text-xs font-semibold text-sky-600 hover:text-sky-800 bg-sky-50 hover:bg-sky-100 px-3 py-1.5 rounded transition-colors cursor-pointer">Detail</button>
                <button onclick="triggerEdit()" class="text-xs font-semibold text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded transition-colors cursor-pointer">Edit</button>
                <button onclick="triggerTerminate()" class="text-xs font-semibold text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded transition-colors cursor-pointer">Putus Langganan</button>
            </div>
            <button onclick="closeActionsModal()" class="text-xs font-medium text-slate-500 hover:text-slate-700 cursor-pointer">Tutup</button>
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

    function openActionsModal(button) {
        const modal = document.getElementById('actions-modal');
        const content = modal.querySelector('.transform');
        
        selectedCustomerData = {
            id: button.getAttribute('data-id'),
            code: button.getAttribute('data-code'),
            name: button.getAttribute('data-name'),
            package: button.getAttribute('data-package'),
            price: button.getAttribute('data-price'),
            address: button.getAttribute('data-address'),
            village: button.getAttribute('data-village'),
            district: button.getAttribute('data-district'),
        };
        
        document.getElementById('actions-modal-title').innerText = selectedCustomerData.name;
        document.getElementById('actions-modal-code').innerText = selectedCustomerData.code;
        
        // Setup static info
        const fullAddress = `${selectedCustomerData.address !== '-' ? selectedCustomerData.address + ', ' : ''}Kel. ${selectedCustomerData.village}, Kec. ${selectedCustomerData.district}`;
        document.getElementById('modal-info-address').innerText = fullAddress;
        document.getElementById('modal-info-package').innerText = selectedCustomerData.package;
        document.getElementById('modal-info-price').innerText = selectedCustomerData.price;
        
        // Show modal and loading state
        document.getElementById('modal-loading').classList.remove('hidden');
        document.getElementById('modal-content').classList.add('hidden');
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            content.classList.remove('scale-95');
            content.classList.add('scale-100');
        }, 10);
        
        // Fetch payment info
        fetch(`/customers/${selectedCustomerData.id}/payment-info`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('modal-loading').classList.add('hidden');
                document.getElementById('modal-content').classList.remove('hidden');
                
                if (data.invoice_id) {
                    document.getElementById('payment-form-container').classList.remove('hidden');
                    document.getElementById('no-invoice-state').classList.add('hidden');
                    
                    document.getElementById('modal-info-period').innerText = `${data.billing_period} (Jatuh Tempo: ${data.due_date || '-'})`;
                    
                    const formatRp = (num) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(num);
                    
                    // Piutang
                    if (data.total_piutang > 0) {
                        document.getElementById('modal-info-arrears').innerText = formatRp(data.total_piutang);
                    } else {
                        document.getElementById('modal-info-arrears').innerText = '-';
                    }
                    
                    // Discount
                    if (data.discount > 0) {
                        document.getElementById('modal-info-discount').innerText = formatRp(data.discount);
                    } else {
                        document.getElementById('modal-info-discount').innerText = '-';
                    }
                    
                    // Total to pay (remaining amount of the specific invoice)
                    document.getElementById('modal-info-total').innerText = formatRp(data.remaining_amount);
                    
                    // Setup form
                    document.getElementById('payment-form').action = `/invoices/${data.invoice_id}/payments`;
                    document.getElementById('payment_amount').value = data.remaining_amount;
                    document.getElementById('payment_amount').max = data.remaining_amount;
                    
                    // Sync allocation to note
                    document.getElementById('payment_note').value = 'Pembayaran: ' + document.getElementById('payment_allocation').value;
                    
                } else {
                    document.getElementById('payment-form-container').classList.add('hidden');
                    document.getElementById('no-invoice-state').classList.remove('hidden');
                    
                    document.getElementById('modal-info-period').innerText = '-';
                    document.getElementById('modal-info-arrears').innerText = '-';
                    document.getElementById('modal-info-discount').innerText = '-';
                    document.getElementById('modal-info-total').innerText = 'Rp 0';
                }
            })
            .catch(err => {
                console.error(err);
                document.getElementById('modal-loading').innerHTML = '<p class="text-red-500 text-sm">Gagal memuat data. Silakan tutup dan coba lagi.</p>';
            });
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
        window.Dialog.show({
            title: 'Konfirmasi Terminasi',
            message: `Apakah Anda yakin ingin melakukan TERMINASI / PEMUTUSAN kontrak layanan untuk ${selectedCustomerData.name} (${selectedCustomerData.code})?`,
            icon: 'error',
            buttons: [
                { text: 'Batal', type: 'secondary' },
                { text: 'Ya, Terminasi', type: 'danger', onClick: () => {
                    window.Dialog.close();
                    window.Toast.info('Terminasi', `Layanan untuk ${selectedCustomerData.name} telah masuk daftar terminasi.`);
                }}
            ]
        });
    }

    function toggleConnection(id, name, checkbox) {
        const isChecked = checkbox.checked;
        const actionText = isChecked ? 'mengaktifkan kembali' : 'mengisolir / menonaktifkan';
        
        window.Confirm(
            'Konfirmasi Perubahan Status',
            `Apakah Anda yakin ingin ${actionText} koneksi internet untuk pelanggan ${name}?`,
            'warning',
            () => {
                // Konfirmasi: lanjutkan action (contoh memanggil Toast)
                window.Toast.success('Koneksi Diubah', `Anda berhasil ${actionText} koneksi internet untuk pelanggan ${name}.`);
                // TODO: tambahkan AJAX call ke backend jika diperlukan di sini
            },
            () => {
                // Batal: kembalikan state checkbox
                checkbox.checked = !isChecked;
            }
        );
    }

    function openDetailModal(button) {
        // Fallback or legacy trigger
        openActionsModal(button);
    }

    function closeDetailModal() {
        const modal = document.getElementById('detail-modal');
        const content = modal.querySelector('.transform');
        content.classList.remove('scale-100');
        content.classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 150);
    }
</script>
@endsection
