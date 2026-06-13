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
            <input type="text" name="search" id="search" value="{{ $search }}" placeholder="Nama, NIK, kode, email, telepon..." class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
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
                    <option value="{{ $package->id }}" {{ $packageId == $package->id ? 'selected' : '' }}>{{ $package->package_code }} - {{ $package->category }}</option>
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
                    $displayId = $customer->cid ?? $customer->customer_code;
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
                        {{ $customer->village->name ?? '-' }}
                    </td>
                    <!-- Paket -->
                    <td class="px-6 py-3.5 whitespace-nowrap text-slate-800 font-semibold">
                        {{ $customer->internetPackage->package_code ?? '-' }}
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
                                data-package="{{ $customer->internetPackage ? $customer->internetPackage->package_code . ' (' . $customer->internetPackage->category . ')' : '-' }}"
                                data-price="{{ $customer->internetPackage ? 'Rp ' . number_format($customer->internetPackage->monthly_price, 0, ',', '.') : '-' }}"
                                data-address="{{ $customer->address }}"
                                data-village="{{ $customer->village->name ?? '-' }}"
                                data-district="{{ $customer->district->name ?? '-' }}"
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

<!-- Customer Action Modal -->
<div id="actions-modal" class="fixed inset-0 bg-slate-900/60 flex items-center justify-center hidden z-40 p-4 transition-all duration-300">
    <div class="bg-white border border-slate-200 rounded-lg w-full max-w-xl shadow-xl overflow-hidden transform scale-95 transition-all duration-300">
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
        <!-- Body (Options Grid) -->
        <div class="p-6">
            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">PILIHAN AKSI OPERASIONAL</span>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Option 1: Detail Info -->
                <button onclick="triggerDetail()" class="flex items-center gap-3 p-3 border border-slate-100 rounded-lg hover:border-sky-200 hover:bg-sky-50/30 transition-all text-left group cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500/25">
                    <div class="p-2 bg-sky-50 text-sky-600 rounded-md group-hover:bg-sky-100 transition-colors">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-800">Detail Pelanggan</span>
                        <span class="block text-[10px] text-slate-400 mt-0.5">Lihat data & profil lengkap</span>
                    </div>
                </button>

                <!-- Option 2: Edit Profil -->
                <button onclick="triggerEdit()" class="flex items-center gap-3 p-3 border border-slate-100 rounded-lg hover:border-sky-200 hover:bg-sky-50/30 transition-all text-left group cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500/25">
                    <div class="p-2 bg-slate-50 text-slate-600 rounded-md group-hover:bg-slate-100 transition-colors">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-slate-800">Ubah Data Profil</span>
                        <span class="block text-[10px] text-slate-400 mt-0.5">Koreksi info alamat / nomor HP</span>
                    </div>
                </button>

                <!-- Option 3: Terminate / Putus Layanan -->
                <button onclick="triggerTerminate()" class="flex items-center gap-3 p-3 border border-red-50/50 rounded-lg hover:border-red-200 hover:bg-red-50/30 transition-all text-left group cursor-pointer focus:outline-none focus:ring-2 focus:ring-red-500/25 sm:col-span-2">
                    <div class="p-2 bg-red-50 text-red-600 rounded-md group-hover:bg-red-100 transition-colors">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <div>
                        <span class="block text-xs font-semibold text-red-800">Putuskan Layanan</span>
                        <span class="block text-[10px] text-red-400 mt-0.5">Terminasi kontrak berlangganan</span>
                    </div>
                </button>
            </div>
        </div>
        <!-- Footer -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex justify-end">
            <button onclick="closeActionsModal()" class="btn-secondary">Tutup</button>
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
            rawCode: button.getAttribute('data-raw-code'),
            name: button.getAttribute('data-name'),
            phone: button.getAttribute('data-phone'),
            email: button.getAttribute('data-email') || '-',
            status: button.getAttribute('data-status'),
            reg: button.getAttribute('data-reg'),
            package: button.getAttribute('data-package'),
            price: button.getAttribute('data-price'),
            address: button.getAttribute('data-address') || '-',
            village: button.getAttribute('data-village'),
            district: button.getAttribute('data-district'),
            btn: button
        };
        
        document.getElementById('actions-modal-title').innerText = selectedCustomerData.name;
        document.getElementById('actions-modal-code').innerText = selectedCustomerData.code;
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            content.classList.remove('scale-95');
            content.classList.add('scale-100');
        }, 10);
    }

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
        if (confirm(`Apakah Anda yakin ingin melakukan TERMINASI / PEMUTUSAN kontrak layanan untuk ${selectedCustomerData.name} (${selectedCustomerData.code})?`)) {
            alert(`[TERMINASI] Layanan untuk ${selectedCustomerData.name} telah masuk daftar terminasi.`);
        }
        closeActionsModal();
    }

    function toggleConnection(id, name, checkbox) {
        const isChecked = checkbox.checked;
        const actionText = isChecked ? 'mengaktifkan kembali' : 'mengisolir / menonaktifkan';
        
        alert(`[KONEKSI] Anda berhasil ${actionText} koneksi internet untuk pelanggan ${name}.`);
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
