@extends('layouts.app')

@section('title', 'Detail Pelanggan: ' . $customer->full_name . ' — Whusnet Operasional')
@section('page_title', 'Detail Pelanggan')

@section('content')
@php
    $completeness = $customer->dataCompleteness();
    $missingRequiredLabels = array_values($completeness['missing_required']);
    $missingOptionalLabels = array_values($completeness['missing_optional']);
    $regDate = \App\Support\IndonesianDate::date($customer->registration_date);
    $latestInvoice = $customer->invoices->first();

    $monthlyPrice  = (float)($customer->customerService?->monthly_price ?? ($customer->internetPackage?->monthly_price ?? 0));
    $discount      = (float)($customer->customerService?->discount ?? ($customer->discount_amount ?? 0));
    $otherFee      = (float)($customer->customerService?->other_fee ?? 0);
    $ppnPercent    = $customer->customerService ? (float)$customer->customerService->ppn : (float)($customer->tax_percent ?? 0);

    $discountedPrice = max(0, $monthlyPrice - $discount);
    $ppnAmount       = round($discountedPrice * ($ppnPercent / 100), 2);
    $totalBill       = $customer->customerService
        ? (float)$customer->customerService->total_monthly_bill
        : ($discountedPrice + $ppnAmount + $otherFee);

    $isActive = in_array($customer->status, ['active', 'suspended']) || $customer->data_completeness_status === 'siap_billing';
@endphp

<!-- LAYER 1: NAKED PAGE HEADER (Strict Design System Rule: No card wrapper) -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-xl font-bold text-slate-900 dark:text-slate-50 tracking-tight">{{ $customer->full_name }}</h1>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold {{ $customer->subscriptionStatus?->badgeClasses() ?? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' }}">
                ● Status: {{ $customer->subscriptionStatus->name ?? Str::headline($customer->status) }}
            </span>
            <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded text-xs font-mono font-medium text-slate-700 dark:text-slate-300">
                <span>{{ $displayIdLabel ?? 'CID' }}: {{ $displayId }}</span>
                <button type="button" onclick="copyText('{{ $displayId }}', 'CID')" class="text-slate-400 hover:text-sky-600 ml-1 cursor-pointer" title="Salin CID">
                    <i class="fa-regular fa-copy"></i>
                </button>
            </div>
            @if($customer->collector)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border border-violet-200 dark:border-violet-500/20" title="Kolektor yang rutin menagih pelanggan ini">
                    Kolektor: {{ $customer->collector->name }}
                </span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-slate-50 dark:bg-slate-800 text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-slate-700">
                    Belum Ada Kolektor
                </span>
            @endif

            @php
                // Total uang lebih yang pernah diserahkan pelanggan ini.
                // Pembayaran DITOLAK tak ikut dijumlah — kalau pembayarannya
                // dibatalkan, lebih bayarnya ikut batal.
                $totalOverpay = $customer->payments
                    ->filter(fn ($p) => $p->payment_status === \App\Enums\PaymentStatus::VALID)
                    ->sum(fn ($p) => (float) $p->overpay_amount);
            @endphp
            @if($totalOverpay > 0)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-500/20" title="Total uang lebih yang pernah diserahkan. Catatan saja — bukan saldo, tidak otomatis dipakai untuk tagihan berikutnya.">
                    Lebih Bayar: Rp {{ number_format($totalOverpay, 0, ',', '.') }}
                </span>
            @endif
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Paket: <strong>{{ $customer->internetPackage ? ($customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name) : 'Belum Ada Paket' }}</strong> (Rp {{ number_format($totalBill, 0, ',', '.') }}/bln) — {{ $customer->pop->name ?? 'POP Belum Set' }} ({{ $customer->miniPop->name ?? 'Mini POP Belum Set' }}) — Terdaftar sejak {{ $regDate }}
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0 flex-wrap">
        @can('customers.detail.installation.activate')
            @php
                $hasWorkflowTask = isset($customerTasks) && $customerTasks->whereIn('task_type', [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value])->isNotEmpty();
                $isProvenLegacyActive = $customer->old_customer_id && $customer->customerService?->request_status === 'ACTIVE';
            @endphp
            @if($customer->data_completeness_status !== 'siap_billing' && $customer->status !== 'active' && !$hasWorkflowTask && $isProvenLegacyActive)
                <form action="{{ route('customers.activate', $customer->id) }}" method="POST" class="inline" onsubmit="event.preventDefault(); window.confirmAction('Pelanggan ini belum aktif lewat proses verifikasi normal. Aktifkan manual sekarang? CID akan dibuat dan tagihan pertama akan diterbitkan.', this);">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 disabled:cursor-not-allowed text-white rounded-lg transition-colors text-xs font-semibold shadow-sm cursor-pointer"
                            title="Khusus pelanggan migrasi lama."
                            @if(!$completeness['is_ready_billing']) disabled title="Data profil belum lengkap untuk diaktifkan" @endif>
                        <i class="fa-solid fa-circle-check"></i>
                        Aktivasi Manual
                    </button>
                </form>
            @endif
        @endcan

        <a href="{{ route('customers.edit', $customer->id) }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg transition-colors text-xs font-semibold shadow-sm">
            <i class="fa-solid fa-pen-to-square"></i>
            Edit Profil
        </a>

        @can('invoices.create')
            @if($isActive && $customer->customerService)
                <button type="button" onclick="openInvoiceModal()" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition-colors text-xs font-semibold shadow-sm cursor-pointer">
                    <i class="fa-solid fa-plus"></i>
                    Buat Tagihan
                </button>
            @endif
        @endcan

        @can('customers.detail.installation.validate')
            <button type="button" x-data @click="$dispatch('open-modal', 'network-assignment')" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors text-xs font-semibold shadow-sm cursor-pointer">
                <i class="fa-solid fa-diagram-project"></i>
                Atur Mini POP
            </button>
        @endcan

        <a href="{{ route('customers.index') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 rounded-lg transition-colors text-xs font-semibold shadow-sm">
            Kembali
        </a>
    </div>
</div>

<!-- LAYER 3: SINGLE UNIFIED DETAIL PANEL (Card Budget = 1) -->
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm overflow-hidden">

    <!-- SECTION A: QUICK METRIC STRIP (Flat summary bar with dividers) -->
    <div class="grid grid-cols-2 md:grid-cols-5 divide-x divide-y md:divide-y-0 divide-slate-200 dark:divide-slate-700 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
        <div class="p-4 flex flex-col justify-center">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">LANGGANAN & BIAYA</span>
            <span class="text-xs font-bold text-slate-900 dark:text-slate-100 mt-1 truncate">{{ $customer->internetPackage->name ?? 'Belum Ada Paket' }}</span>
            <span class="text-xs font-mono font-semibold text-sky-600 dark:text-sky-400">Rp {{ number_format($totalBill, 0, ',', '.') }}/bln (Nett)</span>
        </div>
        <div class="p-4 flex flex-col justify-center">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">IP ADDRESS & PPPOE</span>
            <div class="flex items-center gap-1.5 mt-1 font-mono text-xs font-semibold text-slate-900 dark:text-slate-100">
                <span>{{ $customer->ip_address ?? 'Belum Ada IP' }}</span>
                @if($customer->ip_address)
                <button type="button" onclick="copyText('{{ $customer->ip_address }}', 'IP Address')" class="text-slate-400 hover:text-sky-600 cursor-pointer" title="Salin IP">
                    <i class="fa-regular fa-copy text-[10px]"></i>
                </button>
                @endif
            </div>
            <span class="text-[11px] font-mono text-slate-500 truncate">PPPoE: {{ $customer->customerTechnicalDetail->pppoe_username ?? ($customer->pppoe_username ?? '-') }}</span>
        </div>
        <div class="p-4 flex flex-col justify-center">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">MODEM ONT & SIGNAL</span>
            <span class="text-xs font-mono font-semibold text-slate-900 dark:text-slate-100 mt-1 truncate">{{ $customer->ont_sn ?? ($customer->customerDevice->ont_sn ?? 'Belum Terpasang') }}</span>
            <span class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 font-semibold">Redaman: {{ $customer->customerTechnicalDetail->fiber_signal ?? ($customer->customerDevice->signal_power ?? '-') }}</span>
        </div>
        <div class="p-4 flex flex-col justify-center">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">BILLING & TAGIHAN</span>
            @if($latestInvoice)
                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 mt-1 truncate">{{ $latestInvoice->invoice_number }} ({{ $latestInvoice->invoice_status->label() }})</span>
            @else
                <span class="text-xs font-bold text-slate-400 mt-1">Belum Ada Tagihan</span>
            @endif
            <span class="text-[11px] text-slate-500">Jatuh Tempo: {{ $customer->customerService?->due_date ? 'Tgl ' . \Carbon\Carbon::parse($customer->customerService->due_date)->day . ' Per Bulan' : '-' }}</span>
        </div>
        <div class="p-4 flex flex-col justify-center col-span-2 md:col-span-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">KELENGKAPAN PROFIL</span>
            <div class="flex items-center justify-between mt-1">
                <span class="text-xs font-bold {{ count($completeness['missing_required']) > 0 ? 'text-rose-600' : (count($completeness['missing_optional']) > 0 ? 'text-amber-600' : 'text-emerald-600') }}">{{ $completeness['percentage'] }}% Lengkap</span>
                <span class="text-[10px] font-mono text-slate-400">{{ 28 - count($completeness['missing_required']) - count($completeness['missing_optional']) }}/28 Parameter</span>
            </div>
            <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-1.5 mt-1.5 overflow-hidden">
                <div class="{{ count($completeness['missing_required']) > 0 ? 'bg-rose-500' : (count($completeness['missing_optional']) > 0 ? 'bg-amber-500' : 'bg-emerald-500') }} h-full rounded-full transition-all duration-300" style="width: {{ $completeness['percentage'] }}%"></div>
            </div>
        </div>
    </div>

    <!-- SECTION C: SEARCH & EXACT 15-TAB NAV BAR -->
    <div class="border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
        <!-- Omni-Search Bar & Mode Toggle -->
        <div class="p-4 border-b border-slate-200 dark:border-slate-700 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="omni-search" onkeyup="filterContent()" placeholder="⚡ Cari apapun di seluruh tab (contoh: IP, ZTE, PPPoE, Speedtest, NIK, Prorate, Tiang, Kontrak)..."
                       class="w-full pl-9 pr-10 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg text-xs text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <button type="button" onclick="clearSearch()" id="clear-search-btn" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 p-1 rounded-lg border border-slate-200 dark:border-slate-700 text-xs shrink-0">
                <button type="button" onclick="setViewMode('tabs')" id="view-mode-tabs" class="px-3 py-1.5 rounded-md font-semibold bg-white dark:bg-slate-800 text-sky-600 shadow-sm transition-all cursor-pointer">📑 Mode Tab (15 Tab)</button>
                <button type="button" onclick="setViewMode('all')" id="view-mode-all" class="px-3 py-1.5 rounded-md font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 transition-all cursor-pointer">⚡ All-In-One (Scroll Semua)</button>
            </div>
        </div>

        <!-- 15 Tab Buttons Nav -->
        <div id="tab-nav-wrapper" class="overflow-x-auto flex border-b border-slate-200 dark:border-slate-700 scrollbar-none px-2 bg-slate-50/50 dark:bg-slate-900/30">
            <button type="button" onclick="switchTab('ringkasan')" id="tab-btn-ringkasan" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-sky-600 text-sky-600 whitespace-nowrap cursor-pointer">Ringkasan (Overview)</button>
            @if(auth()->user()->hasPermission('customers.detail.identity.view'))
            <button type="button" onclick="switchTab('identitas')" id="tab-btn-identitas" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Identitas</button>
            @endif
            @if(auth()->user()->hasPermission('customers.detail.address.view'))
            <button type="button" onclick="switchTab('alamat')" id="tab-btn-alamat" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Alamat</button>
            @endif
            <button type="button" onclick="switchTab('pop')" id="tab-btn-pop" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">POP/Cabang</button>
            @if(auth()->user()->hasPermission('customers.detail.survey.view'))
            <button type="button" onclick="switchTab('survey')" id="tab-btn-survey" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Survey</button>
            @endif
            @if(auth()->user()->hasPermission('customers.detail.installation.view'))
            <button type="button" onclick="switchTab('pemasangan')" id="tab-btn-pemasangan" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Pemasangan</button>
            @endif
            @if(auth()->user()->hasPermission('customers.detail.devices.view'))
            <button type="button" onclick="switchTab('perangkat')" id="tab-btn-perangkat" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Perangkat</button>
            @endif
            @if(auth()->user()->hasPermission('customers.detail.packages.view'))
            <button type="button" onclick="switchTab('paket-layanan')" id="tab-btn-paket-layanan" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Paket & Layanan</button>
            @endif
            <button type="button" onclick="switchTab('billing')" id="tab-btn-billing" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Billing</button>
            <button type="button" onclick="switchTab('tagihan')" id="tab-btn-tagihan" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Tagihan</button>
            <button type="button" onclick="switchTab('pembayaran')" id="tab-btn-pembayaran" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Pembayaran</button>
            @if(auth()->user()->hasPermission('customers.detail.documents.view'))
            <button type="button" onclick="switchTab('dokumen')" id="tab-btn-dokumen" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Dokumen & Berkas</button>
            @endif
            <button type="button" onclick="switchTab('riwayat-ticketing')" id="tab-btn-riwayat-ticketing" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Riwayat Ticketing</button>
            <button type="button" onclick="switchTab('riwayat-perubahan')" id="tab-btn-riwayat-perubahan" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Riwayat Perubahan</button>
            @if($customer->customerTechnicalDetail)
            <button type="button" onclick="switchTab('teknis-lama')" id="tab-btn-teknis-lama" class="tab-button px-3.5 py-3 text-xs font-bold border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 whitespace-nowrap cursor-pointer">Detail Teknis Lama</button>
            @endif
        </div>
    </div>

    <!-- SECTION D: UNIFIED DETAILS BODY (All Data Tabs) -->
    <div class="p-6 text-xs" id="details-container">

        <!-- TAB 1: RINGKASAN (OVERVIEW) -->
        <div id="tab-content-ringkasan" class="tab-content space-y-6 searchable-section">
            <!-- Completeness Overview Banner -->
            <div class="border {{ count($completeness['missing_required']) > 0 ? 'border-rose-200 dark:border-rose-800 bg-rose-50/40 dark:bg-rose-950/20' : (count($completeness['missing_optional']) > 0 ? 'border-amber-200 dark:border-amber-800 bg-amber-50/40 dark:bg-amber-950/20' : 'border-emerald-200 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-950/20') }} rounded-lg p-5">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Kelengkapan Data Profil Pelanggan</h3>
                    <span class="text-xs font-semibold text-slate-500">Persentase Terisi: <strong class="text-sky-600 font-bold">{{ $completeness['percentage'] }}%</strong></span>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ count($completeness['missing_required']) > 0 ? 'bg-rose-100 text-rose-700 border border-rose-300' : (count($completeness['missing_optional']) > 0 ? 'bg-amber-100 text-amber-700 border border-amber-300' : 'bg-emerald-100 text-emerald-700 border border-emerald-300') }}">
                        ● {{ Str::headline($customer->data_completeness_status) }}
                    </span>
                    <span class="text-xs text-slate-600 dark:text-slate-300">
                        Profil data terisi <strong class="{{ count($completeness['missing_required']) > 0 ? 'text-rose-600' : 'text-emerald-600' }} font-bold">{{ $completeness['percentage'] }}%</strong> dari total 28 parameter evaluasi sistem.
                    </span>
                </div>
            </div>

            <!-- CARD TIMELINE PROSES ALUR PENGERJAAN -->
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-5 bg-white dark:bg-slate-800">
                <div class="flex flex-wrap gap-2 items-center justify-between mb-3">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">CARD TIMELINE PROSES ALUR PENGERJAAN</span>
                    <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded border {{ $customer->status === 'active' ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 border-emerald-200 dark:border-emerald-800' : 'text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 border-slate-200 dark:border-slate-600' }}">
                        {{ Str::headline($customer->status) }}
                    </span>
                </div>

                @if(! empty($timelineAnomaly))
                    <p class="text-[10px] text-amber-600 dark:text-amber-400 mb-3 italic">
                        Catatan: tanggal registrasi tercatat lebih akhir dari tanggal survey/pemasangan — anomali data hasil migrasi legacy, bukan kesalahan tampilan.
                    </p>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                    @foreach(($timeline ?? []) as $index => $step)
                        @php
                            $stepTone = match($step['status']) {
                                'completed' => 'bg-emerald-50/60 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-800',
                                'current' => 'bg-sky-50/60 dark:bg-sky-950/30 border-sky-200 dark:border-sky-800',
                                'warning' => 'bg-amber-50/60 dark:bg-amber-950/30 border-amber-200 dark:border-amber-800',
                                'danger' => 'bg-rose-50/60 dark:bg-rose-950/30 border-rose-200 dark:border-rose-800',
                                default => 'bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700',
                            };
                            $stepIcon = match($step['status']) {
                                'completed' => 'fa-circle-check text-emerald-500',
                                'current' => 'fa-circle-half-stroke text-sky-500',
                                'warning' => 'fa-triangle-exclamation text-amber-500',
                                'danger' => 'fa-circle-xmark text-rose-500',
                                default => 'fa-circle text-slate-300 dark:text-slate-600',
                            };
                        @endphp
                        <div class="p-3 rounded-lg border {{ $stepTone }}">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <span class="text-[10px] font-bold uppercase {{ $step['status'] === 'completed' ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300' }}">
                                    {{ $index + 1 }}. {{ $step['step'] }}
                                </span>
                                <i class="fa-solid {{ $stepIcon }} text-xs"></i>
                            </div>
                            <span class="block text-xs font-bold text-slate-900 dark:text-slate-100">{{ $step['date'] }}</span>
                            <span class="block text-[10px] font-semibold text-slate-600 dark:text-slate-300">{{ $step['title'] }}</span>
                            <span class="block text-[10px] text-slate-500 font-mono truncate" title="{{ $step['notes'] }}">{{ $step['notes'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- CARD RINGKASAN WAKTU & PENANGGUNG JAWAB PER TAHAP -->
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden bg-white dark:bg-slate-800 shadow-sm">
                <div class="px-5 py-3.5 bg-slate-50/70 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">
                        <i class="fa-solid fa-clock-rotate-left text-sky-600 mr-1.5"></i> Ringkasan Waktu &amp; Penanggung Jawab Per Tahap Workflow
                    </h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Rekapitulasi timestamp eksekusi dan PIC penanggung jawab dari tahap pendaftaran hingga verifikasi aktivasi.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/40 dark:bg-slate-900/30 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                <th class="px-5 py-3">Tahap Alur Workflow</th>
                                <th class="px-5 py-3">Waktu &amp; Tanggal Eksekusi</th>
                                <th class="px-5 py-3">Penanggung Jawab (PIC)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            @php
                                // Class ditulis literal, BUKAN "bg-{$accent}-100": Tailwind 4
                                // memindai teks sumber, jadi class yang dirakit lewat
                                // interpolasi tidak akan pernah ikut ter-generate.
                                $accentClasses = [
                                    'sky' => 'bg-sky-100 dark:bg-sky-950 text-sky-600 dark:text-sky-400',
                                    'indigo' => 'bg-indigo-100 dark:bg-indigo-950 text-indigo-600 dark:text-indigo-400',
                                    'amber' => 'bg-amber-100 dark:bg-amber-950 text-amber-600 dark:text-amber-400',
                                    'purple' => 'bg-purple-100 dark:bg-purple-950 text-purple-600 dark:text-purple-400',
                                    'emerald' => 'bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400',
                                ];
                            @endphp
                            @foreach(($workflowStages ?? []) as $stage)
                                @php
                                    $accent = $accentClasses[$stage['accent']] ?? 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300';
                                    $waktu = $stage['at']
                                        ? \App\Support\IndonesianDate::dateTimeWithSeconds($stage['at'])
                                        : ($stage['date_fallback'] ?: null);
                                    $initials = $stage['pic']
                                        ? strtoupper(mb_substr(preg_replace('/[^\p{L}]/u', '', $stage['pic']), 0, 2))
                                        : '—';
                                @endphp
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="w-6 h-6 shrink-0 rounded-full {{ $accent }} flex items-center justify-center font-bold text-[10px]">{{ $stage['no'] }}</span>
                                            <div>
                                                <span class="block text-xs font-bold text-slate-900 dark:text-slate-100 searchable-text">{{ $stage['title'] }}</span>
                                                <span class="block text-[10px] text-slate-400 font-mono">{{ $stage['subtitle'] }}</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 font-mono searchable-text {{ $waktu ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400' }}">
                                        {{ $waktu ?: 'Belum tercatat' }}
                                    </td>
                                    <td class="px-5 py-3">
                                        @if($stage['pic'])
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 shrink-0 rounded-full {{ $accent }} flex items-center justify-center font-bold text-[10px]">{{ $initials }}</div>
                                                <div>
                                                    <span class="block text-xs font-semibold text-slate-800 dark:text-slate-200 searchable-text">{{ $stage['pic'] }}</span>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-slate-400">Belum ada PIC</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Active Subscription card -->
                <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/30">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">LANGGANAN AKTIF</span>
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->internetPackage ? ($customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name) : 'Belum Ada Paket' }}</h4>
                    <p class="text-xs text-slate-500 mt-1">Kategori: {{ $customer->internetPackage->category ?? '-' }}</p>
                    <div class="flex items-baseline mt-3">
                        <span class="text-lg font-bold text-slate-900 dark:text-slate-100 font-mono searchable-text">Rp {{ number_format($totalBill, 0, ',', '.') }}</span>
                        <span class="text-[10px] text-slate-400 ml-1">/bulan (Nett)</span>
                    </div>
                </div>
                
                <!-- Address Card -->
                <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/30">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">ALAMAT INSTALASI</span>
                    <p class="text-xs text-slate-800 dark:text-slate-200 font-semibold leading-relaxed searchable-text">{{ $customer->address ?? 'Belum diisi' }}</p>
                    <p class="text-[10px] text-slate-500 mt-1.5 searchable-text">
                        Kel. {{ $customer->village->name ?? ($customer->customerAddress->village ?? '-') }}, 
                        Kec. {{ $customer->district->name ?? ($customer->customerAddress->district ?? '-') }}, 
                        Kota/Kab. {{ $customer->city->name ?? ($customer->customerAddress->city ?? '-') }}
                    </p>
                    <div class="mt-2.5 flex items-center gap-1.5 font-mono text-[10px] text-slate-500 searchable-text">
                        <i class="fa-solid fa-location-dot text-sky-600"></i>
                        <span>Lat/Long: {{ $customer->latitude ?? ($customer->customerAddress->latitude ?? '-') }}, {{ $customer->longitude ?? ($customer->customerAddress->longitude ?? '-') }}</span>
                    </div>
                </div>
            </div>

            <!-- Technical summary card -->
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-5">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-3">RINGKASAN TEKNIS JARINGAN</span>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4 text-xs">
                    <div>
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">NOMOR CID / REQ ID</span>
                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400 searchable-text">{{ $displayId }}</span>
                    </div>
                    <div>
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">POP CABANG</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200 searchable-text">{{ $customer->pop->name ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">MINI POP (OLT)</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200 searchable-text">{{ $customer->miniPop->name ?? 'Belum di-assign' }}</span>
                    </div>
                    <div>
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">DISTRIBUSI ODP</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200 searchable-text">{{ $customer->distribution->code ?? 'Belum di-assign' }}</span>
                    </div>
                    <div>
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">IP ADDRESS</span>
                        <span class="font-mono font-semibold text-slate-800 dark:text-slate-200 searchable-text">{{ $customer->ip_address ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">ONT SERIAL NUMBER</span>
                        <span class="font-mono font-semibold text-slate-800 dark:text-slate-200 searchable-text">{{ $customer->ont_sn ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- Missing Fields Alert if applicable -->
            @if(count($completeness['missing_required']) > 0 || count($completeness['missing_optional']) > 0)
            <div class="border border-amber-200 dark:border-amber-800 rounded-lg p-5 bg-amber-50/40 dark:bg-amber-950/20">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">PARAMETER YANG BELUM LENGKAP</span>
                <div class="space-y-3 text-xs">
                    @if(count($completeness['missing_required']) > 0)
                    <div>
                        <span class="font-semibold text-rose-600 block mb-1">Field Wajib (Mencegah Layanan Aktif/Billing):</span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($missingRequiredLabels as $label)
                                <span class="px-2.5 py-0.5 bg-rose-50 text-rose-600 border border-rose-200 rounded text-[10px] font-mono">{{ $label }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if(count($completeness['missing_optional']) > 0)
                    <div>
                        <span class="font-semibold text-amber-600 block mb-1">Field Opsional/Teknis:</span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($missingOptionalLabels as $label)
                                <span class="px-2.5 py-0.5 bg-amber-50 text-amber-600 border border-amber-200 rounded text-[10px] font-mono dark:bg-red-950 dark:border-red-200 dark:text-white">{{ $label }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <!-- TAB 2: IDENTITAS -->
        @if(auth()->user()->hasPermission('customers.detail.identity.view'))
        <div id="tab-content-identitas" class="tab-content hidden space-y-6 searchable-section">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Formulir Identitas Pelanggan</span>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">ID Registrasi (Permanen)</span>
                        <span class="font-mono font-bold text-sky-600 dark:text-sky-400 searchable-text">{{ $customer->customer_code }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nama Lengkap</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->full_name }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nomor Identitas (NIK)</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->identity_number ?? 'Belum diisi' }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Jenis Kelamin</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->gender?->label() ?? 'Belum diisi' }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nomor HP Utama</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->primary_phone ?? 'Belum diisi' }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nomor HP Alternatif</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->alternative_phone ?? 'Belum diisi' }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Alamat Email</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->email ?? 'Belum diisi' }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between md:col-span-2">
                        <span class="text-slate-400">Tanggal Registrasi</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text">{{ $regDate }}</span>
                    </div>
                </div>
            </div>

            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Informasi Referral & Sales</span>
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-3 gap-6 text-xs">
                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/30">
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">ID SALES</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 mt-1 block searchable-text">{{ $customer->sales_code ?? '-' }}</span>
                    </div>
                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/30">
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">KODE AGENT</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 mt-1 block searchable-text">{{ $customer->agent_code ?? '-' }}</span>
                    </div>
                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/30">
                        <span class="block text-[9px] font-bold text-slate-400 uppercase">REFERRAL PELANGGAN</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 mt-1 block searchable-text">{{ $customer->referral_customer_code ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- TAB 3: ALAMAT -->
        @if(auth()->user()->hasPermission('customers.detail.address.view'))
        <div id="tab-content-alamat" class="tab-content hidden space-y-6 searchable-section">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                @php
                    $lat = $customer->latitude ?? ($customer->customerAddress->latitude ?? null);
                    $lng = $customer->longitude ?? ($customer->customerAddress->longitude ?? null);
                @endphp
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 flex flex-wrap gap-2 justify-between items-center">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Alamat Instalasi Detail</span>
                    @if($lat && $lng)
                        <a href="https://maps.google.com/?q={{ $lat }},{{ $lng }}" target="_blank" rel="noopener" class="text-xs text-sky-600 font-semibold hover:underline">Buka di Google Maps ↗</a>
                    @endif
                </div>
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between md:col-span-2">
                        <span class="text-slate-400">Alamat Lengkap</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 text-right searchable-text">{{ $customer->address ?? 'Belum diisi' }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Desa / Kelurahan</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->village->name ?? ($customer->customerAddress->village ?? 'Belum diisi') }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Kecamatan</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->district->name ?? ($customer->customerAddress->district ?? 'Belum diisi') }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Kota / Kabupaten</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->city->name ?? ($customer->customerAddress->city ?? 'Belum diisi') }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Provinsi</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->customerAddress->province ?? 'Jawa Timur' }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Garis Lintang (Latitude)</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->latitude ?? ($customer->customerAddress->latitude ?? 'Belum diisi') }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Garis Bujur (Longitude)</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->longitude ?? ($customer->customerAddress->longitude ?? 'Belum diisi') }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- TAB 4: POP/CABANG -->
        <div id="tab-content-pop" class="tab-content hidden space-y-6 searchable-section">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700 flex justify-between items-center">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Data POP / Cabang Terkoneksi</span>
                    @can('customers.detail.installation.validate')
                    <button type="button" x-data @click="$dispatch('open-modal', 'network-assignment')" class="text-xs text-sky-600 font-semibold hover:underline cursor-pointer">Ubah Penugasan POP →</button>
                    @endcan
                </div>
                @if($customer->pop)
                <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nama POP / Cabang</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->pop->name }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Kode Cabang</span>
                        <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->pop->code }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Tipe Cabang</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 uppercase text-[10px] tracking-wider searchable-text">{{ $customer->pop->type }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Status POP</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-200 uppercase">
                            {{ $customer->pop->status ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between md:col-span-2">
                        <span class="text-slate-400">Alamat Kantor POP</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 text-right searchable-text">
                            {{ $customer->pop->address }}, Kel. {{ $customer->pop->village }}, Kec. {{ $customer->pop->district }}, {{ $customer->pop->city }}
                        </span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Penanggung Jawab (PIC)</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->pop->pic_name ?? 'Belum ditentukan' }}</span>
                    </div>
                    <div class="py-1.5 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nomor HP PIC</span>
                        <span class="font-mono font-medium text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->pop->pic_phone ?? 'Belum ditentukan' }}</span>
                    </div>
                </div>
                @else
                <div class="p-8 text-center text-slate-400 text-xs">
                    Belum ada POP/Cabang yang di-assign untuk pelanggan ini.
                </div>
                @endif
            </div>
        </div>

        <!-- TAB 5: SURVEY -->
        @if(auth()->user()->hasPermission('customers.detail.survey.view'))
        <div id="tab-content-survey" class="tab-content hidden space-y-6 searchable-section">
            @include('customers.tabs._survey')
        </div>
        @endif

        <!-- TAB 6: PEMASANGAN -->
        @if(auth()->user()->hasPermission('customers.detail.installation.view'))
        <div id="tab-content-pemasangan" class="tab-content hidden space-y-6 searchable-section">
            @include('customers.tabs._installation')
        </div>
        @endif

        <!-- TAB 7: PERANGKAT -->
        @if(auth()->user()->hasPermission('customers.detail.devices.view'))
        <div id="tab-content-perangkat" class="tab-content hidden space-y-6 searchable-section">
            @include('customers.tabs._device')
        </div>
        @endif

        <!-- TAB 8: PAKET & LAYANAN -->
        @if(auth()->user()->hasPermission('customers.detail.packages.view'))
        <div id="tab-content-paket-layanan" class="tab-content hidden space-y-6 searchable-section">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Paket Internet & Status Layanan Jaringan</span>
                </div>
                <div class="p-5 space-y-5">
                    @if($customer->internetPackage)
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50/50 dark:bg-slate-900/30">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">KODE LAYANAN</span>
                            <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 mt-1 block searchable-text">{{ $customer->internetPackage->package_code }} - {{ $customer->internetPackage->name }}</span>
                        </div>
                        <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50/50 dark:bg-slate-900/30">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">KATEGORI LAYANAN</span>
                            <span class="font-semibold text-slate-900 dark:text-slate-100 mt-1 block searchable-text">{{ $customer->internetPackage->category }}</span>
                        </div>
                        <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50/50 dark:bg-slate-900/30">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">KECEPATAN (BANDWIDTH)</span>
                            <span class="font-mono font-semibold text-sky-600 dark:text-sky-400 mt-1 block searchable-text">
                                {{ $customer->internetPackage->download_speed_mbps }} Mbps Down / {{ $customer->internetPackage->upload_speed_mbps }} Mbps Up
                            </span>
                        </div>
                        <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-3 bg-slate-50/50 dark:bg-slate-900/30">
                            <span class="block text-[9px] font-bold text-slate-400 uppercase">JENIS KONTRAK</span>
                            <span class="font-semibold text-slate-900 dark:text-slate-100 mt-1 block searchable-text">Rutin Bulanan</span>
                        </div>
                    </div>
                    @else
                    <div class="p-4 text-center text-slate-400">
                        Belum ada paket internet yang terpilih.
                    </div>
                    @endif

                    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50/50 dark:bg-slate-900/30">
                        <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-2">INTEGRASI TEKNIS JARINGAN</span>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 font-mono text-xs">
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">ONT Serial Number</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->ont_sn ?? 'Belum terpasang' }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">IP Address Dialed</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->ip_address ?? 'Belum teralokasi' }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Nama Perangkat OLT</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->olt_code ?? 'Belum diisi' }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Nomor OLT [CID Generator]</span>
                                <span class="font-bold text-sky-600 searchable-text">{{ $displayId }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Kode Kotak ODP</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->odp_code ?? 'Belum terhubung' }}</span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">VLAN ID Jaringan</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->vlan_id ?? 'Belum ditentukan' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- TAB 9: BILLING -->
        <div id="tab-content-billing" class="tab-content hidden space-y-6 searchable-section">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100">Rincian Biaya Bulanan, Biaya Pemasangan Awal & Billing Cycle</span>
                </div>
                <div class="p-5 space-y-6 text-xs">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">BREAKDOWN BIAYA NETT BULANAN</span>
                        <div class="space-y-3 font-mono text-xs">
                            <div class="flex justify-between text-slate-500">
                                <span>Harga Paket Internet</span>
                                <span>Rp {{ number_format($monthlyPrice, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-emerald-600 font-semibold">
                                <span>Potongan Diskon</span>
                                <span>- Rp {{ number_format($discount, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between text-slate-500">
                                <span>PPN ({{ number_format($ppnPercent, 0) }}%)</span>
                                <span>Rp {{ number_format($ppnAmount, 2, ',', '.') }}</span>
                            </div>
                            @if($otherFee > 0)
                            <div class="flex justify-between text-slate-500">
                                <span>Biaya Lain</span>
                                <span>Rp {{ number_format($otherFee, 2, ',', '.') }}</span>
                            </div>
                            @endif
                            <hr class="border-dashed border-slate-200 dark:border-slate-700">
                            <div class="flex justify-between text-xs font-bold text-slate-900 dark:text-slate-100">
                                <span>Total Biaya Per Bulan</span>
                                <span class="text-sky-600">Rp {{ number_format($totalBill, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4">MASA AKTIF & PERIODE</span>
                        <div class="space-y-3 text-xs">
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Tanggal Aktivasi Layanan</span>
                                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    {{ $customer->customerService?->activation_date ? \App\Support\IndonesianDate::date($customer->customerService->activation_date) : 'Belum diaktivasi' }}
                                </span>
                            </div>
                            @if($customer->customerService?->activation_time)
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Waktu Aktivasi</span>
                                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    {{ substr($customer->customerService->activation_time, 0, 5) }} WIB
                                </span>
                            </div>
                            @endif
                            @if($customer->customerService?->activated_by_name || $customer->customerService?->activatedBy)
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Petugas Aktivasi</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    {{ $customer->customerService->activatedBy->name ?? $customer->customerService->activated_by_name ?? '-' }}
                                </span>
                            </div>
                            @endif
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Tanggal Jatuh Tempo Bulanan</span>
                                <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                    {{ $customer->customerService?->due_date ? \App\Support\IndonesianDate::date($customer->customerService->due_date) : 'Belum ditentukan' }}
                                </span>
                            </div>
                            <div class="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-700/50">
                                <span class="text-slate-400">Siklus Billing</span>
                                <span class="font-semibold text-slate-900 dark:text-slate-100 capitalize searchable-text">
                                    {{ $customer->customerService?->billing_cycle ?? 'Monthly' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RINCIAN TAGIHAN AWAL / REGISTRASI — kolom dibaca 1:1 dari tabel
                     `invoices` (prorate_amount, extra_installation_fee, extra_cable_fee,
                     extra_pole_fee, other_fee, discount, ppn). Tidak ada kolom
                     formula/qty di skema, jadi keterangan cuma label deskriptif. --}}
                @php
                    $initialInvoice = $customer->invoices->first(fn ($inv) => in_array($inv->invoice_type?->value, ['awal', 'reaktivasi'], true));
                @endphp
                @if($initialInvoice)
                    @php
                        $initialRows = [
                            ['label' => 'Tagihan Prorate Bulan Pertama', 'column' => 'prorate_amount — proporsional dari tanggal aktivasi sampai akhir periode', 'value' => (float) $initialInvoice->prorate_amount],
                            ['label' => 'Jasa Instalasi & Pemasangan', 'column' => 'extra_installation_fee — default Master Paket + tambahan setting perangkat', 'value' => (float) $initialInvoice->extra_installation_fee],
                            ['label' => 'Biaya Kabel Tambahan', 'column' => 'extra_cable_fee — kabel FO di luar jarak standar', 'value' => (float) $initialInvoice->extra_cable_fee],
                            ['label' => 'Tambahan Tiang', 'column' => 'extra_pole_fee — tiang galvanis penambat udara', 'value' => (float) $initialInvoice->extra_pole_fee],
                            ['label' => 'Biaya Lain-lain', 'column' => 'other_fee', 'value' => (float) $initialInvoice->other_fee],
                        ];
                    @endphp
                    <div class="pt-4 border-t border-slate-200 dark:border-slate-700">
                        <div class="flex flex-wrap gap-2 items-center justify-between mb-3">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                                RINCIAN TAGIHAN AWAL / REGISTRASI ({{ $initialInvoice->invoice_number }})
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide {{ $initialInvoice->invoice_status->value === 'lunas' ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' : 'bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 border-amber-200 dark:border-amber-800' }}">
                                {{ $initialInvoice->invoice_status->label() }}
                            </span>
                        </div>
                        <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50/60 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold text-slate-400 uppercase">
                                        <th class="px-4 py-2">Komponen Biaya Awal</th>
                                        <th class="px-4 py-2">Kolom di Sistem</th>
                                        <th class="px-4 py-2 text-right">Nominal Biaya</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700 font-mono">
                                    @foreach($initialRows as $row)
                                        @if($row['value'] > 0)
                                            <tr>
                                                <td class="px-4 py-2 font-sans font-semibold text-slate-900 dark:text-slate-100">{{ $row['label'] }}</td>
                                                <td class="px-4 py-2 font-sans text-slate-500">{{ $row['column'] }}</td>
                                                <td class="px-4 py-2 text-right font-bold text-slate-900 dark:text-slate-100 searchable-text">Rp {{ number_format($row['value'], 2, ',', '.') }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                    <tr class="bg-slate-50/40 dark:bg-slate-900/30">
                                        <td class="px-4 py-2 font-sans font-semibold text-slate-900 dark:text-slate-100">Subtotal</td>
                                        <td class="px-4 py-2 font-sans text-slate-500">subtotal</td>
                                        <td class="px-4 py-2 text-right font-bold text-slate-900 dark:text-slate-100 searchable-text">Rp {{ number_format((float) $initialInvoice->subtotal, 2, ',', '.') }}</td>
                                    </tr>
                                    <tr class="text-emerald-600 dark:text-emerald-400">
                                        <td class="px-4 py-2 font-sans font-semibold">Potongan Diskon</td>
                                        <td class="px-4 py-2 font-sans text-slate-500">discount</td>
                                        <td class="px-4 py-2 text-right font-bold searchable-text">- Rp {{ number_format((float) $initialInvoice->discount, 2, ',', '.') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-4 py-2 font-sans font-semibold text-slate-900 dark:text-slate-100">PPN</td>
                                        <td class="px-4 py-2 font-sans text-slate-500">ppn</td>
                                        <td class="px-4 py-2 text-right {{ (float) $initialInvoice->ppn > 0 ? 'font-bold text-slate-900 dark:text-slate-100' : 'font-sans text-slate-400' }} searchable-text">
                                            {{ (float) $initialInvoice->ppn > 0 ? 'Rp '.number_format((float) $initialInvoice->ppn, 2, ',', '.') : 'Tidak dikenakan' }}
                                        </td>
                                    </tr>
                                    <tr class="bg-slate-100/60 dark:bg-slate-700/60 font-bold">
                                        <td class="px-4 py-2 font-sans text-slate-900 dark:text-slate-100" colspan="2">TOTAL PEMBAYARAN REGISTRASI AWAL (total_amount)</td>
                                        <td class="px-4 py-2 text-right text-sky-600 dark:text-sky-400 text-sm searchable-text">Rp {{ number_format((float) $initialInvoice->total_amount, 2, ',', '.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
                </div>
            </div>
        </div>

        <!-- TAB 10: TAGIHAN -->
        <div id="tab-content-tagihan" class="tab-content hidden space-y-6 searchable-section">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Riwayat Tagihan Pelanggan</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Daftar invoice tagihan bulanan yang diterbitkan secara manual maupun sistem.</p>
                </div>
                @can('invoices.create')
                    @if($isActive && $customer->customerService)
                        <button type="button" onclick="openInvoiceModal()" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded text-xs font-semibold shadow-sm cursor-pointer">
                            + Buat Tagihan Manual
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
                    ['title' => 'Tagihan Awal / Registrasi', 'rows' => $invoicesAwal, 'badge' => 'bg-amber-50 text-amber-600 border-amber-200'],
                    ['title' => 'Tagihan Bulanan', 'rows' => $invoicesBulanan, 'badge' => 'bg-sky-50 text-sky-600 border-sky-200'],
                ] as $group)
                    <div class="{{ !$loop->first ? 'mt-6' : '' }}">
                        <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">{{ $group['title'] }} ({{ $group['rows']->count() }})</h4>
                        @if($group['rows']->count() > 0)
                            <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm">
                                <table class="w-full text-left border-collapse text-xs">
                                    <thead>
                                        <tr class="bg-slate-50/60 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 font-semibold text-slate-400 uppercase text-[10px]">
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
                                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700 text-slate-700 dark:text-slate-300 font-mono">
                                        @foreach($group['rows'] as $invoice)
                                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors" id="invoice-row-{{ $invoice->id }}">
                                                <td class="px-4 py-3 font-bold text-slate-900 dark:text-slate-100 searchable-text">
                                                    @can('invoices.view')
                                                        <a href="{{ route('invoices.show', $invoice->id) }}" class="text-sky-600 hover:underline">{{ $invoice->invoice_number }}</a>
                                                    @else
                                                        {{ $invoice->invoice_number }}
                                                    @endcan
                                                </td>
                                                <td class="px-4 py-3 font-sans">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide {{ $group['badge'] }}">
                                                        {{ $invoice->invoice_type?->label() ?? ucfirst((string) $invoice->invoice_type) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 searchable-text">{{ $invoice->billing_period }}</td>
                                                <td class="px-4 py-3 font-sans searchable-text">{{ \App\Support\IndonesianDate::date($invoice->issue_date) }}</td>
                                                <td class="px-4 py-3 font-sans searchable-text">{{ \App\Support\IndonesianDate::date($invoice->due_date) }}</td>
                                                <td class="px-4 py-3 text-right font-semibold searchable-text">Rp {{ number_format($invoice->total_amount, 2, ',', '.') }}</td>
                                                <td class="px-4 py-3 text-center font-sans">
                                                    @php
                                                        $statusClass = match($invoice->invoice_status->value) {
                                                            'lunas' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                                                            'sebagian' => 'bg-sky-50 text-sky-600 border-sky-200',
                                                            'belum_dibayar' => 'bg-amber-50 text-amber-600 border-amber-200',
                                                            'batal' => 'bg-rose-50 text-rose-600 border-rose-200',
                                                            default => 'bg-slate-100 text-slate-600 border-slate-200',
                                                        };
                                                        $statusLabel = $invoice->invoice_status->label();
                                                    @endphp
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide {{ $statusClass }}" id="invoice-status-badge-{{ $invoice->id }}">
                                                        {{ $statusLabel }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-slate-400 font-sans searchable-text">{{ $invoice->creator->name ?? 'System' }}</td>
                                                @can('payments.create')
                                                    @php $settled = in_array($invoice->invoice_status->value, ['lunas', 'batal'], true); @endphp
                                                    <td class="px-4 py-3 text-center font-sans" id="invoice-pay-btn-{{ $invoice->id }}">
                                                        <button type="button"
                                                                data-invoice-id="{{ $invoice->id }}"
                                                                data-invoice-number="{{ $invoice->invoice_number }}"
                                                                data-remaining="{{ (float) $invoice->remaining_amount }}"
                                                                {{-- Target POST dirender server-side (ADHOC-20 langkah 3). --}}
                                                                data-payment-store-url="{{ route('invoices.payments.store', $invoice->id) }}"
                                                                onclick="openQuickPaymentModal(parseInt(this.dataset.invoiceId, 10), this.dataset.invoiceNumber, parseFloat(this.dataset.remaining), this.dataset.paymentStoreUrl)"
                                                                class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-[10px] font-bold uppercase tracking-wide shadow-sm cursor-pointer {{ $settled ? 'hidden' : '' }}">
                                                            Bayar
                                                        </button>
                                                        <span class="text-slate-400 text-[10px] {{ $settled ? '' : 'hidden' }}">—</span>
                                                    </td>
                                                @endcan
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="py-6 text-center text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg text-xs">
                                Belum ada {{ strtolower($group['title']) }}.
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="py-12 text-center text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                    <i class="fa-solid fa-file-invoice text-3xl mb-2 text-slate-300"></i>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">Belum Ada Tagihan Terbit</h4>
                    <p class="text-[11px] text-slate-500 mt-1">Gunakan tombol "Buat Tagihan Manual" untuk membuat invoice pertama pelanggan.</p>
                </div>
            @endif
        </div>

        <!-- TAB 11: PEMBAYARAN -->
        <div id="tab-content-pembayaran" class="tab-content hidden space-y-6 searchable-section">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                <div>
                    <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Riwayat Pembayaran Pelanggan</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Pembayaran yang terhubung ke invoice pelanggan ini.</p>
                </div>
            </div>

            @if($customer->payments && $customer->payments->count() > 0)
                <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/60 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700 font-semibold text-slate-400 uppercase text-[10px]">
                                <th class="px-4 py-3">No. Pembayaran</th>
                                <th class="px-4 py-3">No. Tagihan</th>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Metode</th>
                                <th class="px-4 py-3 text-right">Nominal</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Bukti</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 font-mono">
                            @foreach($customer->payments as $payment)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors">
                                    <td class="px-4 py-3 font-bold text-slate-900 dark:text-slate-100 searchable-text">{{ $payment->payment_number }}</td>
                                    <td class="px-4 py-3 searchable-text">
                                        @can('invoices.view')
                                            <a href="{{ route('invoices.show', $payment->invoice_id) }}" class="text-sky-600 hover:underline">{{ $payment->invoice->invoice_number ?? '-' }}</a>
                                        @else
                                            {{ $payment->invoice->invoice_number ?? '-' }}
                                        @endcan
                                    </td>
                                    <td class="px-4 py-3 font-sans searchable-text">{{ \App\Support\IndonesianDate::date($payment->payment_date) }}</td>
                                    <td class="px-4 py-3 font-sans uppercase searchable-text">{{ strtoupper($payment->payment_method->value ?? (string)$payment->payment_method) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-slate-100 searchable-text">
                                        Rp {{ number_format((float) $payment->amount, 2, ',', '.') }}
                                        @if((float) $payment->overpay_amount > 0)
                                            <span class="block text-[10px] font-semibold text-sky-600 dark:text-sky-400" title="Uang lebih yang diserahkan pelanggan — catatan saja, tidak menambah pembayaran tagihan">
                                                +{{ number_format((float) $payment->overpay_amount, 0, ',', '.') }} lebih
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 font-sans">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide bg-emerald-50 text-emerald-600 border-emerald-200">
                                            {{ $payment->payment_status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-sans">
                                        @if($payment->proof_file)
                                            <a href="{{ asset('storage/' . $payment->proof_file) }}" target="_blank" class="text-sky-600 hover:underline font-semibold">Lihat bukti ↗</a>
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
                <div class="py-12 text-center text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                    <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-300"></i>
                    <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">Belum Ada Riwayat Pembayaran</h4>
                    <p class="text-[11px] text-slate-500 mt-1">Pembayaran akan tampil setelah kasir/finance mencatat pembayaran invoice.</p>
                </div>
            @endif
        </div>

        <!-- TAB 12: DOKUMEN & BERKAS -->
        <div id="tab-content-dokumen" class="tab-content hidden space-y-6 searchable-section">
            @if(auth()->user()->hasPermission('customers.detail.documents.view'))
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">LAMPIRAN DOKUMEN PENDUKUNG</h3>
                        <p class="text-[11px] text-slate-500 mt-0.5">Dokumen disimpan aman & private sesuai hak akses permission sistem.</p>
                    </div>
                    @if(auth()->user()->hasPermission('upload_customer_documents'))
                    <button type="button" onclick="openModal('document-upload-modal')" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-700 text-white rounded text-xs font-semibold cursor-pointer">
                        + Upload Dokumen Baru
                    </button>
                    @endif
                </div>

                @if($customer->documents->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($customer->documents->sortByDesc('created_at') as $document)
                            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden flex flex-col justify-between hover:border-sky-500 transition-colors shadow-sm bg-white dark:bg-slate-800">
                                <div class="p-4 bg-slate-50/50 dark:bg-slate-900/50 flex items-center justify-center h-36 border-b border-slate-200 dark:border-slate-700">
                                    @if($document->isImage())
                                        <img src="{{ route('customers.documents.show', $document->id) }}" alt="{{ $document->typeLabel() }}" class="max-h-28 max-w-full rounded object-contain shadow-sm">
                                    @else
                                        <div class="h-24 w-24 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded-lg flex flex-col items-center justify-center text-rose-600 shadow-sm">
                                            <i class="fa-solid fa-file-pdf text-3xl mb-1"></i>
                                            <span class="text-[10px] font-bold">PDF FILE</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="p-3 flex items-center justify-between gap-3 text-xs">
                                    <div class="min-w-0">
                                        <span class="block text-xs font-semibold text-slate-900 dark:text-slate-100 truncate searchable-text">{{ $document->typeLabel() }}</span>
                                        <span class="block text-[10px] text-slate-400 font-mono mt-0.5">{{ $document->created_at?->format('d/m/Y H:i') }}</span>
                                        <span class="block text-[10px] text-slate-400 truncate">Upload: {{ $document->uploader?->name ?? '-' }}</span>
                                    </div>
                                    <a href="{{ route('customers.documents.show', $document->id) }}" target="_blank" class="shrink-0 p-1.5 text-sky-600 hover:bg-slate-100 dark:hover:bg-slate-700 rounded" title="Buka Dokumen">
                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-12 text-center text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                        <i class="fa-solid fa-folder-open text-3xl mb-2 text-slate-300"></i>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">Belum Ada Dokumen</h4>
                        <p class="text-[11px] text-slate-500 mt-1">Dokumen rumah, kontrak, survey, dan foto pemasangan akan tampil di sini setelah diupload.</p>
                    </div>
                @endif
            @else
                <div class="py-12 text-center text-slate-400 bg-slate-50/50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100">Akses dokumen dibatasi</h4>
                    <p class="text-[11px] text-slate-500 mt-1">User Anda tidak memiliki permission untuk melihat dokumen pelanggan.</p>
                </div>
            @endif
        </div>

        <!-- TAB 13: RIWAYAT TICKETING -->
        <div id="tab-content-riwayat-ticketing" class="tab-content hidden space-y-6 searchable-section">
            @include('customers.tabs._riwayat_ticketing')
        </div>

        <!-- TAB 14: RIWAYAT PERUBAHAN -->
        <div id="tab-content-riwayat-perubahan" class="tab-content hidden space-y-6 searchable-section">
            @include('customers.tabs._riwayat_perubahan')
        </div>

        <!-- TAB 15: DETAIL TEKNIS LAMA -->
        @if($customer->customerTechnicalDetail)
        <div id="tab-content-teknis-lama" class="tab-content hidden space-y-6 searchable-section">
            <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 bg-slate-50/50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
                    <span class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Detail Teknis Jaringan Lama (Hasil Migrasi Database)</span>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-xs">
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">ID Report Lama</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->customerTechnicalDetail->old_report_id ?? '-' }}</span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">ID Request/Layanan Lama</span>
                        <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->customerTechnicalDetail->old_request_id ?? '-' }}</span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Tipe Koneksi Jaringan</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->customerTechnicalDetail->connection_type ?? '-' }}</span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">ONT Serial Number</span>
                        <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->customerTechnicalDetail->router_or_ont_serial ?? '-' }}</span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">IP Address Dialed</span>
                        <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->customerTechnicalDetail->ip_address ?? '-' }}</span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Nomor ODP / Port</span>
                        <span class="font-semibold text-slate-900 dark:text-slate-100 searchable-text">{{ $customer->customerTechnicalDetail->odp_number ?? '-' }} / {{ $customer->customerTechnicalDetail->odp_port ?? '-' }}</span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Redaman Optik Kabel</span>
                        <span class="font-mono font-bold text-sky-600 searchable-text">{{ $customer->customerTechnicalDetail->fiber_signal ?? '-' }}</span>
                    </div>
                    <div class="py-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between">
                        <span class="text-slate-400">Hasil Test Speed</span>
                        <span class="font-mono font-bold text-emerald-600 searchable-text">{{ $customer->customerTechnicalDetail->test_download ?? '-' }} Mbps Down / {{ $customer->customerTechnicalDetail->test_upload ?? '-' }} Mbps Up</span>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

<!-- TOAST NOTIFICATION -->
<div id="toast" class="fixed bottom-5 right-5 z-50 bg-slate-900 text-white text-xs px-4 py-2.5 rounded-lg shadow-lg flex items-center gap-2 transition-all duration-300 translate-y-10 opacity-0 pointer-events-none">
    <i class="fa-solid fa-circle-check text-emerald-400"></i>
    <span id="toast-message">Disalin!</span>
</div>

<!-- MODALS SECTION -->
<!-- MODAL: Manual Invoice -->
@can('invoices.create')
    @if($isActive && $customer->customerService)
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
        <div id="manual-invoice-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
            <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Buat Tagihan Manual</h3>
                    <button type="button" onclick="closeInvoiceModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>
                <form action="{{ route('customers.invoices.manual', $customer->id) }}" method="POST">
                    @csrf
                    <div class="p-6 space-y-4 text-xs">
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Pelanggan: {{ $customer->full_name }}</span>
                            <span class="text-xs font-bold text-slate-900 dark:text-slate-100">{{ $customer->customerService->package_name_snapshot }} (Rp {{ number_format($totalBill, 0, ',', '.') }})</span>
                        </div>
                        <div>
                            <label for="billing_period" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Periode Tagihan</label>
                            <input type="month" name="billing_period" id="billing_period" value="{{ $defaultPeriod }}" required class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg font-mono text-xs text-slate-800 dark:text-slate-200">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tanggal Terbit & Jatuh Tempo</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="date" name="issue_date" id="issue_date" value="{{ $defaultIssueDate }}" required class="px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg font-mono text-xs text-slate-800 dark:text-slate-200">
                                <input type="date" name="due_date" id="due_date" value="{{ $defaultDueDate }}" required class="px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg font-mono text-xs text-slate-800 dark:text-slate-200">
                            </div>
                        </div>
                        <div>
                            <label for="invoice_type" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Jenis Tagihan</label>
                            <select name="invoice_type" id="invoice_type" required class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg font-semibold text-xs text-slate-800 dark:text-slate-200">
                                <option value="bulanan" {{ $customer->invoices->count() > 0 ? 'selected' : '' }}>Tagihan Bulanan Rutin</option>
                                <option value="awal" {{ $customer->invoices->count() === 0 ? 'selected' : '' }}>Tagihan Awal (PSB)</option>
                                <option value="reaktivasi">Tagihan Reaktivasi</option>
                            </select>
                        </div>
                        <div>
                            <label for="prorate_amount" class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tagihan Prorate (Opsional)</label>
                            {{-- data-rupiah butuh type="text"; batas nilai
                                 ditegakkan validasi server. --}}
                            <input type="text" inputmode="decimal" data-rupiah name="prorate_amount" id="prorate_amount" value="0" oninput="recalcInvoiceTotal()" class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg font-mono text-xs text-slate-800 dark:text-slate-200">
                        </div>
                        <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
                            <button type="button" onclick="closeInvoiceModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300 cursor-pointer">Batal</button>
                            <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg shadow-sm cursor-pointer">Proses Tagihan</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endcan

<!-- MODAL: Document Upload Modal -->
@if(auth()->user()->hasPermission('upload_customer_documents'))
<div id="document-upload-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 w-full max-w-md overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider">Upload Dokumen Pelanggan Baru</h3>
            <button type="button" onclick="closeModal('document-upload-modal')" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('customers.documents.store', ['customer' => $customer->id]) }}" enctype="multipart/form-data">
            @csrf
            <div class="p-5 space-y-4 text-xs">
                <div>
                    <label for="document_type" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Jenis Dokumen</label>
                    <select name="document_type" id="document_type" class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200" required>
                        @foreach(\App\Enums\DocumentType::cases() as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="document_file" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">File Gambar / PDF</label>
                    <input type="file" name="document_file" id="document_file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/*,application/pdf" class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200" required>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" onclick="closeModal('document-upload-modal')" class="px-4 py-2 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-700 dark:text-slate-300 cursor-pointer">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white font-semibold rounded-lg shadow-sm cursor-pointer">Upload File</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@include('payments.partials.quick-payment-modal')

{{-- Realtime tanpa reload buat tab Tagihan — sama pola dengan
     invoices/index.blade.php. Tabel di sini gak nampilin kolom Sisa,
     jadi cuma badge status & tombol Bayar yang perlu di-patch. --}}
@push('scripts')
    <script>
        const CUSTOMER_INVOICE_STATUS_LABELS = {
            belum_dibayar: 'Belum Dibayar',
            sebagian: 'Sebagian',
            lunas: 'Lunas',
            batal: 'Batal',
        };

        const CUSTOMER_INVOICE_STATUS_BADGE_CLASSES = {
            lunas: 'bg-emerald-50 text-emerald-600 border-emerald-200',
            sebagian: 'bg-sky-50 text-sky-600 border-sky-200',
            belum_dibayar: 'bg-amber-50 text-amber-600 border-amber-200',
            batal: 'bg-rose-50 text-rose-600 border-rose-200',
        };

        function applyCustomerInvoiceUpdate(data) {
            const row = document.getElementById('invoice-row-' + data.invoice_id);
            if (!row) {
                return false;
            }

            const badge = document.getElementById('invoice-status-badge-' + data.invoice_id);
            if (badge) {
                badge.textContent = data.invoice_status_label || CUSTOMER_INVOICE_STATUS_LABELS[data.invoice_status] || data.invoice_status;
                badge.className = 'inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide ' +
                    (CUSTOMER_INVOICE_STATUS_BADGE_CLASSES[data.invoice_status] || CUSTOMER_INVOICE_STATUS_BADGE_CLASSES.belum_dibayar);
            }

            const payCell = document.getElementById('invoice-pay-btn-' + data.invoice_id);
            if (payCell) {
                const settled = data.invoice_status === 'lunas' || data.invoice_status === 'batal';
                const btn = payCell.querySelector('button');
                const dash = payCell.querySelector('span');
                if (btn) {
                    btn.classList.toggle('hidden', settled);
                    if (!settled) {
                        btn.dataset.remaining = data.remaining_amount;
                    }
                }
                if (dash) {
                    dash.classList.toggle('hidden', !settled);
                }
            }

            return true;
        }

        // Aksi sendiri lewat modal Bayar Cepat.
        document.addEventListener('payment-recorded', function (e) {
            if (applyCustomerInvoiceUpdate(e.detail)) {
                e.preventDefault();
            }
        });

        // Aksi user lain di POP yang sama, lewat Reverb.
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof window.Echo === 'undefined' || !window.Echo) {
                return;
            }

            window.Echo.private('invoices.{{ $customer->pop_id }}')
                .listen('.InvoiceStatusUpdated', applyCustomerInvoiceUpdate);
        });
    </script>
@endpush

<!-- MODAL: Network Assignment -->
@if(auth()->user()->hasPermission('customers.detail.installation.validate'))
<x-ui.modal name="network-assignment" title="Atur Mini POP & Distribusi" maxWidth="sm">
    <div x-data="{ miniPopId: '{{ old('mini_pop_id', $customer->mini_pop_id) }}' }">
        <div class="pb-3 mb-4 border-b border-slate-200 dark:border-slate-700 space-y-1">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Pelanggan</span>
                    <span class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $customer->full_name }}</span>
                </div>
                <div class="text-right">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">ID Jaringan</span>
                    <span class="font-mono text-xs text-sky-600 font-bold bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">{{ $displayId }}</span>
                </div>
            </div>
            <div class="text-xs text-slate-500 pt-1 flex items-center gap-1">
                <span>Cabang: <strong class="text-slate-700 dark:text-slate-300 font-semibold">{{ $customer->pop->name ?? '-' }} ({{ $customer->pop->pop_code ?? '' }})</strong></span>
            </div>
        </div>

        <form action="{{ route('customers.network-assignment.update', $customer) }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Mini POP (OLT)</label>
                <select name="mini_pop_id" x-model="miniPopId" class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 outline-none cursor-pointer">
                    <option value="">— Belum di-assign —</option>
                    @foreach($availableMiniPops as $miniPop)
                        <option value="{{ $miniPop->id }}">[{{ $miniPop->pop_code }}] {{ $miniPop->name }}</option>
                    @endforeach
                </select>
                @if($availableMiniPops->isEmpty())
                    <div class="text-[11px] text-amber-600 mt-1">
                        Belum ada Mini POP terdaftar di bawah Cabang POP ini.
                    </div>
                @endif
            </div>

            <div class="space-y-1.5">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500">Distribusi</label>
                <select name="distribution_id" class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 outline-none cursor-pointer">
                    <option value="">— Belum di-assign —</option>
                    @foreach($availableDistributions as $dist)
                        <option value="{{ $dist->id }}"
                                x-show="miniPopId == {{ $dist->pop_id }}"
                                {{ old('distribution_id', $customer->distribution_id) == $dist->id ? 'selected' : '' }}>
                            [{{ $dist->code }}] {{ $dist->name }}
                        </option>
                    @endforeach
                </select>
                <p class="text-[11px] text-slate-400">Daftar Distribusi otomatis mengikuti Mini POP yang dipilih di atas.</p>
            </div>

            <div class="flex justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-700 mt-4">
                <button type="button" @click="$dispatch('close-modal', 'network-assignment')"
                        class="text-xs font-semibold px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 cursor-pointer">Batal</button>
                <button type="submit" class="text-xs font-semibold px-5 py-2 rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm cursor-pointer">Simpan</button>
            </div>
        </form>
    </div>
</x-ui.modal>
@endif
@endsection

@section('scripts')
<script>
    let currentViewMode = 'tabs'; // 'tabs' or 'all'

    function switchTab(tabId) {
        if (currentViewMode === 'all') {
            setViewMode('tabs');
        }

        // Hide all tab panels
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
        });
        
        // Show active tab panel
        const activeTab = document.getElementById('tab-content-' + tabId);
        if (activeTab) {
            activeTab.classList.remove('hidden');
        }

        // Reset active state for tab buttons
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('border-sky-600', 'text-sky-600');
            btn.classList.add('border-transparent', 'text-slate-500');
        });

        // Add active state to clicked tab button
        const activeBtn = document.getElementById('tab-btn-' + tabId);
        if (activeBtn) {
            activeBtn.classList.add('border-sky-600', 'text-sky-600');
            activeBtn.classList.remove('border-transparent', 'text-slate-500');
            activeBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    function setViewMode(mode) {
        currentViewMode = mode;
        const btnTabs = document.getElementById('view-mode-tabs');
        const btnAll = document.getElementById('view-mode-all');
        const navWrapper = document.getElementById('tab-nav-wrapper');

        if (mode === 'all') {
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('hidden');
            });
            if (navWrapper) navWrapper.classList.add('hidden');

            if (btnAll) {
                btnAll.classList.add('bg-white', 'dark:bg-slate-800', 'text-sky-600', 'shadow-sm');
                btnAll.classList.remove('text-slate-600', 'dark:text-slate-400');
            }
            if (btnTabs) {
                btnTabs.classList.remove('bg-white', 'dark:bg-slate-800', 'text-sky-600', 'shadow-sm');
                btnTabs.classList.add('text-slate-600', 'dark:text-slate-400');
            }
        } else {
            if (navWrapper) navWrapper.classList.remove('hidden');
            if (btnTabs) {
                btnTabs.classList.add('bg-white', 'dark:bg-slate-800', 'text-sky-600', 'shadow-sm');
                btnTabs.classList.remove('text-slate-600', 'dark:text-slate-400');
            }
            if (btnAll) {
                btnAll.classList.remove('bg-white', 'dark:bg-slate-800', 'text-sky-600', 'shadow-sm');
                btnAll.classList.add('text-slate-600', 'dark:text-slate-400');
            }
            switchTab('ringkasan');
        }
    }

    function filterContent() {
        const query = document.getElementById('omni-search')?.value.toLowerCase().trim() || '';
        const clearBtn = document.getElementById('clear-search-btn');
        const sections = document.querySelectorAll('.searchable-section');

        if (query.length > 0) {
            if (clearBtn) clearBtn.classList.remove('hidden');
            if (currentViewMode === 'tabs') {
                document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('hidden'));
            }
        } else {
            if (clearBtn) clearBtn.classList.add('hidden');
            if (currentViewMode === 'tabs') {
                switchTab('ringkasan');
            }
        }

        sections.forEach(sec => {
            if (query === '') {
                if (currentViewMode === 'all') sec.classList.remove('hidden');
            } else {
                const text = sec.innerText.toLowerCase();
                if (text.includes(query)) {
                    sec.classList.remove('hidden');
                } else {
                    sec.classList.add('hidden');
                }
            }
        });
    }

    function clearSearch() {
        const input = document.getElementById('omni-search');
        if (input) {
            input.value = '';
            filterContent();
            input.focus();
        }
    }

    function copyText(text, label) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            const toast = document.getElementById('toast');
            const msg = document.getElementById('toast-message');
            if (msg) msg.innerText = label + ' disalin: ' + text;
            if (toast) {
                toast.classList.remove('translate-y-10', 'opacity-0', 'pointer-events-none');
                setTimeout(() => {
                    toast.classList.add('translate-y-10', 'opacity-0', 'pointer-events-none');
                }, 2500);
            }
        });
    }

    function openModal(id) { document.getElementById(id)?.classList.remove('hidden'); }
    function closeModal(id) { document.getElementById(id)?.classList.add('hidden'); }

    function openInvoiceModal() { openModal('manual-invoice-modal'); }
    function closeInvoiceModal() { closeModal('manual-invoice-modal'); }

    // Modal milik partial tab Pemasangan & Perangkat. Sebelumnya fungsi ini HANYA
    // ada di customers/fieldwork.blade.php, jadi tombol "Isi Data Pemasangan" /
    // "Isi Laporan Uji" / "Isi Ubah Data Perangkat" di halaman Detail Pelanggan
    // memanggil fungsi yang tidak pernah terdefinisi — klik tidak melakukan apa pun.
    function openInstallationModal() { openModal('installation-modal'); }
    function closeInstallationModal() { closeModal('installation-modal'); }
    function openTestReportModal() { openModal('test-report-modal'); }
    function closeTestReportModal() { closeModal('test-report-modal'); }
    function openDeviceModal() { openModal('device-modal'); }
    function closeDeviceModal() { closeModal('device-modal'); }

    const BASE_NETT = {{ (float)$totalBill }};
    function recalcInvoiceTotal() {
        // Kolom prorata bermasking ribuan — parseFloat('50.000') = 50, dan
        // pratinjau total tagihan akan berbohong tanpa parser ini.
        const prorateEl = document.getElementById('prorate_amount');
        const prorate = (prorateEl && window.Rupiah ? window.Rupiah.angka(prorateEl.value) : parseFloat(prorateEl?.value || 0)) || 0;
        const total   = BASE_NETT + prorate;
        const fmt = v => 'Rp ' + Math.round(v).toLocaleString('id-ID');
        const totalEl = document.getElementById('preview-total');
        if (totalEl) totalEl.textContent = fmt(total);
    }
</script>
@endsection
