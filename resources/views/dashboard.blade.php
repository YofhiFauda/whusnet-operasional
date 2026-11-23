@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_title', 'Dashboard Operasional')
@section('breadcrumb_parent', 'Dashboard')
@section('breadcrumb_parent_url', route('dashboard'))
@section('meta_description', 'Ringkasan kondisi pelanggan, keuangan, gangguan internet, pekerjaan lapangan, dan stok gudang')

{{--
    Dashboard Owner — halaman Type C (Design.md §1.3): page header, filter
    bar, dan tab bar NAKED; konten = grid panel, satu panel per grafik/daftar,
    isi panel flat (divider 1px). Satu skala radius `rounded-md` (= 8px di
    token app.css; `rounded-lg` di proyek ini 12px). Warna status hanya di
    badge/teks/garis-kiri, tidak pernah background. Komponen bersama di
    resources/views/components/dashboard/.
--}}

@section('content')
@php
    $currency = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $pct = fn ($value) => $value === null ? '-' : number_format((float) $value, 1, ',', '.') . '%';
    // Kartu modul Fase 2 (ADHOC-313). Key yang gerbangnya tertutup memang
    // gak ada di array ini (DashboardController::moduleStats()), jadi view
    // cukup cek isset — jangan cek permission lagi di sini.
    $modules = $stats['modules'] ?? [];
    $revenue = $modules['revenue_quality'] ?? ['mrr' => 0, 'arpu' => null, 'churn_rate' => null];
    $packageChanges = $modules['package_changes'] ?? ['upgrade_count' => 0, 'downgrade_count' => 0, 'monthly_delta' => 0];

    $statusLabels = [
        'draft' => 'Draft',
        'perlu_dilengkapi' => 'Perlu Dilengkapi',
        'lengkap' => 'Lengkap',
        'siap_billing' => 'Siap Billing',
    ];

    $pulse = $stats['operational_pulse'] ?? [
        'finance' => ['status' => 'good', 'label' => 'Sehat'],
        'network' => ['status' => 'good', 'label' => 'Normal'],
        'field' => ['status' => 'good', 'label' => 'Lancar'],
        'warehouse' => ['status' => 'good', 'label' => 'Aman'],
        'overall' => ['status' => 'good', 'label' => 'Semua Aman'],
    ];
    $pulseVariant = fn (string $status) => match ($status) {
        'good' => 'success',
        'warning' => 'warning',
        'danger' => 'error',
        default => 'neutral',
    };

    $activeTicketsCount = ($stats['ticket_bucket_masuk'] ?? 0) + ($stats['ticket_bucket_diproses'] ?? 0);
    $overdueFieldCount = ($stats['fop_overdue_psb_count'] ?? 0) + ($stats['fop_overdue_survey_count'] ?? 0);
    $cashInField = ($stats['cash_at_office_amount'] ?? 0) + ($stats['cash_with_collector_amount'] ?? 0);
    $pendingVerifications = ($modules['registration_verification_pending'] ?? 0)
        + ($modules['business_verification_pending'] ?? 0)
        + ($modules['creq_billing_verification_pending'] ?? 0);

    $tabs = [
        'all' => 'Semua',
        'finance' => 'Keuangan',
        'ops' => 'Pemasangan & Gangguan',
        'crm_warehouse' => 'Pelanggan & Gudang',
    ];

    // Tabel ringkas: header lebih gelap dari panel di dua mode (Design.md §18.4).
    $thClass = 'px-5 py-2 text-[10px] font-semibold uppercase tracking-[0.07em] text-text-muted';
    $theadClass = 'bg-slate-100/70 dark:bg-slate-900/50';
@endphp

<div x-data="{ activeTab: 'all' }" class="space-y-6">

    {{-- ── PAGE HEADER (naked, §1.7) ──────────────────────────────────── --}}
    <header class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
        <div class="flex flex-col gap-1">
            <h1 class="text-[22px] font-bold text-text-main leading-tight">Dashboard Operasional</h1>
            <p class="text-[13px] text-text-muted">
                Ringkasan cepat: bagian mana yang aman dan mana yang perlu segera ditindak
                &middot; <span class="font-mono">{{ $filters['period_label'] }}</span>
            </p>
        </div>

        {{-- Denyut operasional: satu badge per bidang. Warna = status, teks = makna. --}}
        <div class="flex flex-col gap-2 lg:items-end">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-semibold uppercase tracking-[0.07em] text-text-muted">Kondisi Usaha Saat Ini</span>
                <x-ui.badge :variant="$pulseVariant($pulse['overall']['status'])">{{ $pulse['overall']['label'] }}</x-ui.badge>
            </div>
            <div class="flex flex-wrap gap-1.5 lg:justify-end">
                <x-ui.badge :variant="$pulseVariant($pulse['finance']['status'])">Keuangan: {{ $pulse['finance']['label'] }}</x-ui.badge>
                <x-ui.badge :variant="$pulseVariant($pulse['network']['status'])">Jaringan: {{ $pulse['network']['label'] }}</x-ui.badge>
                <x-ui.badge :variant="$pulseVariant($pulse['field']['status'])">Lapangan: {{ $pulse['field']['label'] }}</x-ui.badge>
                <x-ui.badge :variant="$pulseVariant($pulse['warehouse']['status'])">Gudang: {{ $pulse['warehouse']['label'] }}</x-ui.badge>
            </div>
        </div>
    </header>

    {{-- ── FILTER BAR (naked, §1.8) + TAB BAR ─────────────────────────── --}}
    <div class="space-y-3">
        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-2.5">
            <label for="pop_id" class="sr-only">Cabang</label>
            <x-ui.select name="pop_id" id="pop_id" class="w-full sm:w-64">
                <option value="">Semua cabang yang bisa Anda lihat</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" @selected((string) $filters['pop_id'] === (string) $pop->id)>{{ $pop->name }}</option>
                @endforeach
            </x-ui.select>

            <div class="flex items-center gap-2">
                <label for="period_from" class="sr-only">Dari bulan</label>
                <x-ui.input type="month" name="period_from" id="period_from" value="{{ $filters['period_from'] }}" class="w-40" />
                <span class="text-xs text-text-muted">s/d</span>
                <label for="period_to" class="sr-only">Sampai bulan</label>
                <x-ui.input type="month" name="period_to" id="period_to" value="{{ $filters['period_to'] }}" class="w-40" />
            </div>

            <div class="flex items-center gap-2">
                <x-ui.button type="submit" variant="primary">Tampilkan</x-ui.button>
                <x-ui.button variant="secondary" href="{{ route('dashboard') }}">Reset</x-ui.button>
            </div>
        </form>

        <nav class="flex gap-1 overflow-x-auto border-b border-border" aria-label="Bagian dashboard">
            @foreach($tabs as $key => $label)
                <button type="button" @click="activeTab = '{{ $key }}'"
                    :class="activeTab === '{{ $key }}' ? 'border-primary text-primary font-semibold' : 'border-transparent text-text-secondary hover:text-text-main'"
                    :aria-current="activeTab === '{{ $key }}' ? 'page' : null"
                    class="shrink-0 -mb-px border-b-2 px-3 py-2 text-[13px] transition-colors cursor-pointer">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ── ROW 1: METRIC CARDS (§5.2) ─────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <x-dashboard.metric label="Pelanggan Aktif" :value="number_format($stats['active_customers'])">
            <x-slot:footer>
                <span>Terdaftar <span class="font-mono text-text-secondary">{{ number_format($stats['total_customers']) }}</span></span>
                <span class="font-mono font-semibold {{ $stats['net_customer_growth'] >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">
                    {{ $stats['net_customer_growth'] >= 0 ? '+' : '' }}{{ number_format($stats['net_customer_growth']) }} bersih
                </span>
            </x-slot:footer>
        </x-dashboard.metric>

        <x-dashboard.metric label="Uang Masuk" :value="$currency($stats['total_payments_amount'])" valueTone="good">
            <x-slot:footer>
                <span>Ditagih <span class="font-mono text-text-secondary">{{ $currency($stats['total_invoices_amount']) }}</span></span>
                <span class="font-mono font-semibold text-text-main">{{ $stats['collection_rate'] !== null ? $stats['collection_rate'].'% terbayar' : '-' }}</span>
            </x-slot:footer>
        </x-dashboard.metric>

        <x-dashboard.metric label="Tagihan Belum Dibayar" :value="$currency($stats['total_unpaid_amount'])"
            :accent="$stats['due_invoices_count'] > 0 ? 'error' : null" :valueTone="$stats['due_invoices_count'] > 0 ? 'danger' : 'default'">
            <x-slot:footer>
                <span><span class="font-mono">{{ number_format($stats['due_invoices_count']) }}</span> tagihan</span>
                <span>bulan-bulan lalu</span>
            </x-slot:footer>
        </x-dashboard.metric>

        <x-dashboard.metric label="Uang Tunai Belum Disetor" :value="$currency($cashInField)" :accent="$cashInField > 0 ? 'warning' : null">
            <x-slot:footer>
                <span>Kasir <span class="font-mono text-text-secondary">{{ $currency($stats['cash_at_office_amount'] ?? 0) }}</span></span>
                <span>Kolektor <span class="font-mono text-text-secondary">{{ $currency($stats['cash_with_collector_amount'] ?? 0) }}</span></span>
            </x-slot:footer>
        </x-dashboard.metric>

        <x-dashboard.metric label="Tiket Gangguan Belum Selesai" :value="number_format($activeTicketsCount)"
            :accent="($stats['ticket_sla_breach_count'] ?? 0) > 0 ? 'error' : null">
            <x-slot:footer>
                <span>Tepat waktu <span class="font-mono text-text-secondary">{{ $stats['ticket_sla_compliance_rate'] ?? 100 }}%</span></span>
                <span class="font-mono {{ ($stats['ticket_sla_breach_count'] ?? 0) > 0 ? 'font-semibold text-rose-700 dark:text-rose-300' : '' }}">{{ number_format($stats['ticket_sla_breach_count'] ?? 0) }} terlambat</span>
            </x-slot:footer>
        </x-dashboard.metric>

        <x-dashboard.metric label="Pasang Baru Selesai" :value="number_format($stats['fop_psb_completed_count'] ?? 0)"
            :accent="$overdueFieldCount > 0 ? 'warning' : null">
            <x-slot:footer>
                <span>Rata-rata <span class="font-mono text-text-secondary">{{ is_null($stats['fop_psb_avg_lead_time_days'] ?? null) ? '-' : $stats['fop_psb_avg_lead_time_days'].' hari' }}</span></span>
                <span class="font-mono {{ $overdueFieldCount > 0 ? 'font-semibold text-amber-700 dark:text-amber-300' : '' }}">{{ number_format($overdueFieldCount) }} terlambat</span>
            </x-slot:footer>
        </x-dashboard.metric>
    </div>

    {{-- ── ROW 2: KEUANGAN & PERLU SEGERA DITINDAK ────────────────────── --}}
    <div x-show="activeTab === 'all' || activeTab === 'finance'" x-transition class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <div class="xl:col-span-2 space-y-6">
            <x-dashboard.panel title="Tagihan vs Uang Masuk per Bulan"
                subtitle="Total tagihan yang dikirim ke pelanggan dibanding uang yang benar-benar diterima, 6 bulan terakhir.">
                <x-slot:actions>
                    <span class="flex items-center gap-1.5 text-xs text-text-secondary"><span class="w-2.5 h-2.5 rounded-sm bg-primary"></span>Total Tagihan</span>
                    <span class="flex items-center gap-1.5 text-xs text-text-secondary"><span class="w-2.5 h-2.5 rounded-sm bg-success"></span>Uang Masuk</span>
                </x-slot:actions>
                <div class="h-68 relative">
                    <canvas id="chart-financial-trend"></canvas>
                </div>
            </x-dashboard.panel>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <x-dashboard.panel title="Cara Pelanggan Membayar" subtitle="Uang masuk menurut cara bayar, periode {{ $filters['period_label'] }}.">
                    <div class="h-52 relative flex items-center justify-center">
                        <canvas id="chart-payment-channels"></canvas>
                    </div>
                    <dl class="mt-4 pt-2 border-t border-border divide-y divide-border text-[13px]">
                        @foreach($stats['payment_channels'] ?? [] as $chan)
                            <x-dashboard.module-row :label="$chan['label']" :value="$currency($chan['amount'])" />
                        @endforeach
                    </dl>
                </x-dashboard.panel>

                <x-dashboard.panel title="Lama Tagihan Belum Dibayar"
                    subtitle="Makin lama telat dibayar, makin sulit ditagih. Kejar yang paling lama dulu.">
                    <x-slot:actions>
                        <x-ui.badge :variant="$stats['due_invoices_count'] > 0 ? 'error' : 'neutral'">{{ number_format($stats['due_invoices_count']) }} belum lunas</x-ui.badge>
                    </x-slot:actions>
                    <div class="h-52 relative">
                        <canvas id="chart-aging-receivables"></canvas>
                    </div>
                    <dl class="mt-4 pt-2 border-t border-border divide-y divide-border text-[13px]">
                        <x-dashboard.module-row label="Belum jatuh tempo" :value="$currency($stats['aging_receivables']['lancar']['amount'] ?? 0)" tone="good" />
                        <x-dashboard.module-row label="Telat 1–30 hari" :value="$currency($stats['aging_receivables']['days_1_30']['amount'] ?? 0)" tone="warning" />
                        <x-dashboard.module-row label="Telat 31–60 hari" :value="$currency($stats['aging_receivables']['days_31_60']['amount'] ?? 0)" tone="warning" />
                        <x-dashboard.module-row label="Telat >60 hari (macet)" :value="$currency($stats['aging_receivables']['days_over_60']['amount'] ?? 0)" tone="danger" />
                    </dl>
                </x-dashboard.panel>
            </div>

            @if($canViewCash)
                <x-dashboard.panel title="Posisi Uang Tunai Saat Ini"
                    subtitle="Uang tunai dari pelanggan yang masih dipegang kasir cabang atau kolektor, belum masuk ke bank/pemilik.">
                    <x-slot:actions>
                        @if(($stats['cash_deposit_open_difference_count'] ?? 0) > 0)
                            <x-ui.badge variant="error">{{ $stats['cash_deposit_open_difference_count'] }} setoran kasir tidak cocok</x-ui.badge>
                        @endif
                        @if(($stats['collector_shortfall_count'] ?? 0) > 0)
                            <x-ui.badge variant="error">{{ $stats['collector_shortfall_count'] }} kurang setor kolektor</x-ui.badge>
                        @endif
                    </x-slot:actions>
                    <x-dashboard.stat-strip class="-mb-5 border-b-0">
                        <x-dashboard.stat label="Di Kasir Cabang" :value="$currency($stats['cash_at_office_amount'])" sub="Belum disetor ke bank/pemilik" />
                        <x-dashboard.stat label="Di Tangan Kolektor" :value="$currency($stats['cash_with_collector_amount'])" sub="Hasil penagihan belum disetor" />
                        <x-dashboard.stat label="Setoran Belum Dicek" :value="$currency($stats['cash_deposit_pending_amount'])" tone="primary"
                            :sub="number_format($stats['cash_deposit_pending_count']).' setoran menunggu dicek'" />
                        <x-dashboard.stat label="Kurang Setor Kolektor" :value="$currency($stats['collector_shortfall_amount'] ?? 0)"
                            :tone="($stats['collector_shortfall_count'] ?? 0) > 0 ? 'danger' : 'default'"
                            :sub="number_format($stats['collector_shortfall_count'] ?? 0).' kolektor setorannya kurang'" />
                    </x-dashboard.stat-strip>
                </x-dashboard.panel>
            @endif
        </div>

        <div class="space-y-6">
            {{-- Perlu Segera Ditindak — baris flat, urgensi lewat badge + warna judul. --}}
            <x-dashboard.panel title="Perlu Segera Ditindak">
                <div class="divide-y divide-border">
                    @if($canViewCash)
                        <div class="py-3 first:pt-0 last:pb-0">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="text-[13px] font-semibold text-text-main">Setoran Kas Perlu Dicek</span>
                                <span class="font-mono text-xs font-semibold text-primary">{{ $pendingCashDeposits->count() }} menunggu</span>
                            </div>
                            <div class="divide-y divide-border">
                                @forelse($pendingCashDeposits as $deposit)
                                    <a href="{{ route('cash-deposits.index') }}" class="flex items-center justify-between gap-3 py-2 text-xs hover:text-primary">
                                        <span class="min-w-0">
                                            <span class="block font-semibold text-text-main truncate">{{ $deposit->pop?->name ?? 'Cabang' }}</span>
                                            <span class="block text-text-muted">{{ $deposit->depositor?->name ?? '-' }} &middot; <span class="font-mono">{{ $deposit->submitted_at?->format('d/m H:i') }}</span></span>
                                        </span>
                                        <span class="font-mono font-semibold text-text-main shrink-0">{{ $currency($deposit->declared_amount) }}</span>
                                    </a>
                                @empty
                                    <p class="text-xs text-text-muted py-1">Semua setoran kas sudah terverifikasi.</p>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    @if($canViewTickets && ($stats['ticket_sla_breach_count'] ?? 0) > 0)
                        <x-dashboard.action-item tone="error" :title="$stats['ticket_sla_breach_count'].' Tiket Gangguan Terlambat Ditangani'"
                            :href="auth()->user()->hasPermission('noc_worksheet.view') ? route('noc.worksheet') : null" linkLabel="Tiket">
                            Segera tindak lanjuti sebelum pelanggan komplain.
                        </x-dashboard.action-item>
                    @endif

                    @if(($modules['isolation']['failed'] ?? 0) > 0)
                        <x-dashboard.action-item tone="error" :title="number_format($modules['isolation']['failed']).' Isolir / Buka Isolir Gagal'"
                            :href="route('customers.isolir-queue.index')" linkLabel="Antrean">
                            Pelanggan nunggak masih jalan, atau sudah bayar tapi masih terputus.
                        </x-dashboard.action-item>
                    @endif

                    @if(($modules['modem_reconciliation']['anomaly'] ?? 0) > 0)
                        <x-dashboard.action-item tone="error" :title="number_format($modules['modem_reconciliation']['anomaly']).' Modem Terjual Tidak Tertagih'"
                            :href="route('reports.modem-sale-reconciliation.index')" linkLabel="Cek">
                            Modem sudah keluar gudang tapi tagihannya tidak akan terbit sendiri.
                        </x-dashboard.action-item>
                    @endif

                    @if($canViewWarehouse && ($stats['low_stock_count'] ?? 0) > 0)
                        <x-dashboard.action-item tone="warning" :title="$stats['low_stock_count'].' Barang Hampir Habis'"
                            :href="route('warehouse.index')" linkLabel="Gudang">
                            Segera kirim barang, atau minta kiriman dari cabang lain.
                        </x-dashboard.action-item>
                    @endif

                    @if(($modules['retrieval_reviews_pending'] ?? 0) > 0)
                        <x-dashboard.action-item tone="warning" :title="number_format($modules['retrieval_reviews_pending']).' Gagal Ambil Alat Perlu Diputuskan'"
                            :href="route('device-retrieval-reviews.index')" linkLabel="Review">
                            Ulangi pengambilan atau bebaskan alatnya.
                        </x-dashboard.action-item>
                    @endif

                    @if($canViewFopDelivery && $overdueFieldCount > 0)
                        <x-dashboard.action-item tone="warning" title="Pekerjaan Lapangan Terlambat" :href="route('fop.dashboard')" linkLabel="Jadwal">
                            {{ number_format($stats['fop_overdue_survey_count'] ?? 0) }} survei lewat 1 hari &middot; {{ number_format($stats['fop_overdue_psb_count'] ?? 0) }} pasang baru lewat 3 hari
                        </x-dashboard.action-item>
                    @endif

                    @if($pendingVerifications > 0)
                        <x-dashboard.action-item tone="neutral" :title="number_format($pendingVerifications).' Menunggu Verifikasi'">
                            {{ number_format($modules['registration_verification_pending'] ?? 0) }} pendaftaran &middot;
                            {{ number_format($modules['business_verification_pending'] ?? 0) }} pelanggan bisnis &middot;
                            {{ number_format($modules['creq_billing_verification_pending'] ?? 0) }} tagihan pekerjaan tambahan
                        </x-dashboard.action-item>
                    @endif
                </div>
            </x-dashboard.panel>

            @can('sales_omset_dashboard.view')
                <x-dashboard.panel title="Pendapatan per Sumber Pelanggan" :href="route('business-development.sales-omset.index')">
                    @php
                        $omsetRows = [
                            ['label' => 'Tim Sales', 'amount' => $stats['omset_sales_amount'] ?? 0, 'sub' => number_format($stats['omset_sales_count'] ?? 0).' pelanggan aktif'],
                            ['label' => 'Dari Teknisi', 'amount' => $stats['omset_teknisi_amount'] ?? 0, 'sub' => number_format($stats['omset_teknisi_count'] ?? 0).' pelanggan aktif'],
                            ['label' => 'Pelanggan Bisnis', 'amount' => $stats['omset_bisnis_amount'] ?? 0, 'sub' => number_format($stats['omset_bisnis_count'] ?? 0).' pelanggan perusahaan/usaha'],
                        ];
                    @endphp
                    <div class="space-y-4">
                        @foreach($omsetRows as $row)
                            <div>
                                <div class="flex justify-between gap-2 text-[13px] mb-1.5">
                                    <span class="text-text-secondary">{{ $row['label'] }}</span>
                                    <span class="font-mono font-semibold tabular-nums text-text-main">{{ $currency($row['amount']) }}</span>
                                </div>
                                {{-- Bar proporsi satu warna (primary): bedanya dibaca dari panjang, bukan warna. --}}
                                <div class="h-1.5 rounded-full bg-surface-muted overflow-hidden">
                                    <div class="h-full bg-primary rounded-full" style="width: {{ min(100, max(2, ($row['amount'] / max(1, $stats['total_invoices_amount'])) * 100)) }}%"></div>
                                </div>
                                <p class="text-[11px] text-text-muted mt-1">{{ $row['sub'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </x-dashboard.panel>
            @endcan

            @php
                $quickLinks = array_filter([
                    auth()->user()->hasPermission('customers.view') ? ['Pelanggan', route('customers.index')] : null,
                    auth()->user()->hasPermission('invoices.view') ? ['Daftar Tagihan', route('invoices.index')] : null,
                    auth()->user()->hasPermission('noc_dashboard.view') ? ['Dashboard NOC', route('noc.dashboard')] : null,
                    auth()->user()->hasPermission('task.view.all') ? ['Dashboard FOP', route('fop.dashboard')] : null,
                    $canViewWarehouse ? ['Gudang', route('warehouse.index')] : null,
                    auth()->user()->hasPermission('collector_worksheet.view') ? ['Worksheet Kolektor', route('collector-worksheet.index')] : null,
                ]);
            @endphp
            <x-dashboard.panel title="Menu Cepat">
                <nav class="-my-2 divide-y divide-border">
                    @forelse($quickLinks as [$label, $url])
                        <a href="{{ $url }}" class="flex items-center justify-between py-2 text-[13px] text-text-secondary hover:text-primary">
                            <span>{{ $label }}</span>
                            <svg class="w-3.5 h-3.5 text-text-disabled" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                        </a>
                    @empty
                        <p class="py-2 text-xs text-text-muted">Tidak ada akses cepat yang tersedia untuk peran Anda.</p>
                    @endforelse
                </nav>
            </x-dashboard.panel>
        </div>
    </div>

    {{-- ── ROW 2B: KUALITAS PENDAPATAN (Fase 2, ADHOC-313) ─────────────── --}}
    <div x-show="activeTab === 'all' || activeTab === 'finance'" x-transition class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        <x-dashboard.module-card title="Pendapatan Bulanan Tetap" subtitle="Dari tagihan bulanan pelanggan aktif saat ini.">
            <x-dashboard.module-row label="Pendapatan bulanan (MRR)" :value="$currency($revenue['mrr'])" />
            <x-dashboard.module-row label="Rata-rata per pelanggan (ARPU)" :value="$revenue['arpu'] === null ? '-' : $currency($revenue['arpu'])" />
            <x-dashboard.module-row label="Pelanggan putus (churn)" :value="$pct($revenue['churn_rate'])"
                :tone="($revenue['churn_rate'] ?? 0) >= 5 ? 'danger' : (($revenue['churn_rate'] ?? 0) >= 2 ? 'warning' : 'default')" />
        </x-dashboard.module-card>

        <x-dashboard.module-card title="Ganti Paket" subtitle="Periode {{ $filters['period_label'] }}.">
            <x-dashboard.module-row label="Naik paket" :value="number_format($packageChanges['upgrade_count'])" tone="good" />
            <x-dashboard.module-row label="Turun paket" :value="number_format($packageChanges['downgrade_count'])"
                :tone="$packageChanges['downgrade_count'] > 0 ? 'warning' : 'default'" />
            <x-dashboard.module-row label="Selisih tagihan bulanan" :value="($packageChanges['monthly_delta'] < 0 ? '- ' : '+ ') . $currency(abs($packageChanges['monthly_delta']))"
                :tone="$packageChanges['monthly_delta'] < 0 ? 'danger' : 'good'" />
        </x-dashboard.module-card>

        <x-dashboard.module-card title="Pelanggan Baru per Agen" subtitle="5 agen teratas, periode {{ $filters['period_label'] }}.">
            @forelse($modules['top_agents'] ?? [] as $agent)
                <x-dashboard.module-row :label="$agent['name']" :value="number_format($agent['total'])" />
            @empty
                <p class="py-2 text-text-muted">Belum ada pelanggan baru di periode ini.</p>
            @endforelse
        </x-dashboard.module-card>

        @isset($modules['collector_performance'])
            @php $collector = $modules['collector_performance']; @endphp
            <x-dashboard.module-card title="Hasil Kunjungan Kolektor" subtitle="Periode {{ $filters['period_label'] }}."
                :href="route('reports.collector-monthly.index')" linkLabel="Laporan"
                :accent="$collector['broken_promises'] > 0 ? 'error' : null">
                <x-dashboard.module-row label="Total kunjungan" :value="number_format($collector['total'])" />
                @foreach(\App\Enums\VisitResult::cases() as $result)
                    <x-dashboard.module-row :label="$result->label()" :value="number_format($collector['by_result'][$result->value] ?? 0)" />
                @endforeach
                <x-dashboard.module-row label="Kunjungan yang berujung bayar" :value="$pct($collector['conversion_rate'])" />
                <x-dashboard.module-row label="Janji bayar sudah lewat" :value="number_format($collector['broken_promises'])"
                    :tone="$collector['broken_promises'] > 0 ? 'danger' : 'default'" />
            </x-dashboard.module-card>
        @endisset

        @isset($modules['billing_waivers'])
            <x-dashboard.module-card title="Bulan yang Tidak Ditagih" subtitle="Jumlah bulan dibebaskan, periode {{ $filters['period_label'] }}.">
                @foreach(\App\Enums\BillingWaiverSource::cases() as $source)
                    <x-dashboard.module-row :label="$source->label()" :value="number_format($modules['billing_waivers'][$source->value] ?? 0) . ' bulan'" />
                @endforeach
            </x-dashboard.module-card>
        @endisset

        @isset($modules['modem_reconciliation'])
            @php $modem = $modules['modem_reconciliation']; @endphp
            <x-dashboard.module-card title="Modem Terjual Belum Ditagih" :href="route('reports.modem-sale-reconciliation.index')"
                :accent="$modem['anomaly'] > 0 ? 'error' : null">
                <x-dashboard.module-row label="Menunggu proses tagihan" :value="number_format($modem['waiting'])" />
                <x-dashboard.module-row label="Bermasalah (perlu dicek)" :value="number_format($modem['anomaly'])"
                    :tone="$modem['anomaly'] > 0 ? 'danger' : 'default'" />
                <x-dashboard.module-row label="Nilai belum ditagih" :value="$currency($modem['unbilled_amount'])"
                    :tone="$modem['unbilled_amount'] > 0 ? 'warning' : 'default'" />
            </x-dashboard.module-card>
        @endisset
    </div>

    {{-- ── ROW 3: PEMASANGAN & GANGGUAN ───────────────────────────────── --}}
    <div x-show="activeTab === 'all' || activeTab === 'ops'" x-transition class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <x-dashboard.panel title="Perjalanan Calon Pelanggan Sampai Aktif"
            subtitle="Tiap tahap dari daftar, survei lokasi, pemasangan, sampai internet aktif dan siap ditagih."
            :href="$canViewFopDelivery ? route('fop.dashboard') : null" linkLabel="Dashboard FOP">
            <x-dashboard.stat-strip>
                <x-dashboard.stat label="1. Menunggu Survei" :value="number_format($stats['funnel_waiting_survey'] ?? 0)"
                    :sub="($stats['fop_overdue_survey_count'] ?? 0) > 0 ? $stats['fop_overdue_survey_count'].' terlambat' : 'Dijadwalkan FOP'" />
                <x-dashboard.stat label="2. Menunggu Persetujuan" :value="number_format($stats['funnel_waiting_acc'] ?? 0)" sub="Dicek sales/admin" />
                <x-dashboard.stat label="3. Menunggu Dipasang" :value="number_format($stats['funnel_waiting_installation'] ?? 0)" tone="primary"
                    :sub="($stats['fop_overdue_psb_count'] ?? 0) > 0 ? $stats['fop_overdue_psb_count'].' terlambat' : 'Antre pasang'" />
                <x-dashboard.stat label="4. Pengecekan Akhir" :value="number_format($stats['funnel_verification_admin'] ?? 0)" sub="Sebentar lagi aktif" />
            </x-dashboard.stat-strip>
            <x-dashboard.stat-strip cols="3" class="border-t-0">
                <x-dashboard.stat label="Survei Selesai" :value="number_format($stats['fop_survey_completed_count'] ?? 0)" sub="Di periode terpilih" />
                <x-dashboard.stat label="Pasang Baru Selesai" :value="number_format($stats['fop_psb_completed_count'] ?? 0)" tone="good" sub="Sudah online" />
                <x-dashboard.stat label="Rata-rata Lama Pasang" :value="is_null($stats['fop_psb_avg_lead_time_days'] ?? null) ? '-' : $stats['fop_psb_avg_lead_time_days'].' hari'" sub="Target maks. 3 hari" />
            </x-dashboard.stat-strip>
            <div class="pt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-text-muted">
                <span>Pendaftaran batal/ditolak <span class="font-mono font-semibold text-text-main">{{ number_format($stats['rejected_customers'] ?? 0) }}</span></span>
                <span>Diputus sementara (isolir) <span class="font-mono font-semibold text-violet-700 dark:text-violet-300">{{ number_format($stats['suspended_customers'] ?? 0) }}</span></span>
            </div>
        </x-dashboard.panel>

        <x-dashboard.panel title="Penanganan Gangguan Internet"
            subtitle="Laporan gangguan yang baru masuk, sedang ditangani, dan selesai, plus penyebab yang paling sering."
            :href="auth()->user()->hasPermission('noc_dashboard.view') ? route('noc.dashboard') : null" linkLabel="Dashboard NOC">
            <x-dashboard.stat-strip>
                <x-dashboard.stat label="Baru Masuk" :value="number_format($stats['ticket_bucket_masuk'] ?? 0)" />
                <x-dashboard.stat label="Sedang Ditangani" :value="number_format($stats['ticket_bucket_diproses'] ?? 0)" tone="primary" />
                <x-dashboard.stat label="Selesai" :value="number_format($stats['ticket_bucket_selesai'] ?? 0)" tone="good" />
                <x-dashboard.stat label="Dibatalkan" :value="number_format($stats['ticket_bucket_dibatalkan'] ?? 0)" tone="muted" />
            </x-dashboard.stat-strip>
            <div class="pt-4">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <span class="text-[10px] font-semibold uppercase tracking-[0.07em] text-text-muted">5 Gangguan Paling Sering</span>
                    <span class="text-[11px] font-mono text-text-muted">{{ $filters['period_label'] }}</span>
                </div>
                <div class="h-44 relative">
                    <canvas id="chart-top-issues"></canvas>
                </div>
            </div>
        </x-dashboard.panel>
    </div>

    {{-- ── ROW 3B: LAYANAN, VERIFIKASI & LAPANGAN (Fase 2, ADHOC-313) ─── --}}
    <div x-show="activeTab === 'all' || activeTab === 'ops'" x-transition class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">

        @isset($modules['isolation'])
            @php $isolation = $modules['isolation']; @endphp
            <x-dashboard.module-card title="Pelanggan Diisolir" subtitle="Kondisi saat ini."
                :href="route('customers.isolir-queue.index')" linkLabel="Antrean"
                :accent="$isolation['failed'] > 0 ? 'error' : null">
                <x-dashboard.module-row label="Menunggu diisolir" :value="number_format($isolation['waiting'])" />
                <x-dashboard.module-row label="Siap / sedang diisolir" :value="number_format($isolation['ready'])" />
                <x-dashboard.module-row label="Sedang terisolir" :value="number_format($isolation['suspended'])" />
                <x-dashboard.module-row label="Proses dibuka kembali" :value="number_format($isolation['restoration'])" />
                <x-dashboard.module-row label="Gagal isolir / gagal dibuka" :value="number_format($isolation['failed'])"
                    :tone="$isolation['failed'] > 0 ? 'danger' : 'default'" />
            </x-dashboard.module-card>
        @endisset

        @if(isset($modules['termination_reasons']) || isset($modules['deac_queue']) || isset($modules['retrieval_reviews_pending']))
            <x-dashboard.module-card title="Putus Langganan & Ambil Alat" subtitle="Alasan putus, periode {{ $filters['period_label'] }}."
                :href="isset($modules['termination_reasons']) ? route('customers.terminated') : null">
                @isset($modules['termination_reasons'])
                    @forelse($modules['termination_reasons'] as $reason)
                        <x-dashboard.module-row :label="$reason['name']" :value="number_format($reason['total'])" />
                    @empty
                        <p class="py-2 text-text-muted">Tidak ada pelanggan putus di periode ini.</p>
                    @endforelse
                @endisset
                @isset($modules['deac_queue'])
                    <x-dashboard.module-row label="Alat belum diambil (task DEAC)" :value="number_format($modules['deac_queue'])"
                        :tone="$modules['deac_queue'] > 0 ? 'warning' : 'default'" />
                @endisset
                @isset($modules['retrieval_reviews_pending'])
                    <x-dashboard.module-row label="Gagal ambil alat, perlu diputuskan" :value="number_format($modules['retrieval_reviews_pending'])"
                        :href="route('device-retrieval-reviews.index')"
                        :tone="$modules['retrieval_reviews_pending'] > 0 ? 'danger' : 'default'" />
                @endisset
            </x-dashboard.module-card>
        @endif

        @if(isset($modules['registration_verification_pending']) || isset($modules['business_verification_pending']) || isset($modules['creq_billing_verification_pending']))
            <x-dashboard.module-card title="Menunggu Verifikasi" subtitle="Kondisi saat ini.">
                @isset($modules['registration_verification_pending'])
                    <x-dashboard.module-row label="Pendaftaran pelanggan baru" :value="number_format($modules['registration_verification_pending'])"
                        :href="route('customer-registration-verifications.index')"
                        :tone="$modules['registration_verification_pending'] > 0 ? 'warning' : 'default'" />
                @endisset
                @isset($modules['business_verification_pending'])
                    <x-dashboard.module-row label="Pelanggan bisnis (BD)" :value="number_format($modules['business_verification_pending'])"
                        :href="route('business-development-verifications.index')"
                        :tone="$modules['business_verification_pending'] > 0 ? 'warning' : 'default'" />
                @endisset
                @isset($modules['creq_billing_verification_pending'])
                    <x-dashboard.module-row label="Tagihan pekerjaan tambahan (C-REQ/MTN)" :value="number_format($modules['creq_billing_verification_pending'])"
                        :href="route('tasks.creq-billing.index')"
                        :tone="$modules['creq_billing_verification_pending'] > 0 ? 'warning' : 'default'" />
                @endisset
            </x-dashboard.module-card>
        @endif

        @isset($modules['non_psb_tasks'])
            @php $nonPsb = $modules['non_psb_tasks']; @endphp
            <x-dashboard.module-card title="Pekerjaan Lapangan Lainnya" subtitle="Selesai periode ini / masih antre."
                :href="route('fop.dashboard')" linkLabel="Jadwal">
                @foreach($nonPsb['by_type'] as $type => $count)
                    <x-dashboard.module-row :label="\App\Enums\TaskType::from($type)->label()"
                        :value="number_format($count['completed']) . ' / ' . number_format($count['queued'])" />
                @endforeach
                <x-dashboard.module-row label="Tertunda (perlu dijadwal ulang)" :value="number_format($nonPsb['pending'])"
                    :tone="$nonPsb['pending'] > 0 ? 'warning' : 'default'" />
                <x-dashboard.module-row label="Laporan belum dikirim teknisi" :value="number_format($nonPsb['lapor_nanti'])"
                    :tone="$nonPsb['lapor_nanti'] > 0 ? 'warning' : 'default'" />
            </x-dashboard.module-card>
        @endisset

        @isset($modules['port_occupancy'])
            @php $ports = $modules['port_occupancy']; @endphp
            <x-dashboard.module-card title="Kapasitas Port Jaringan" subtitle="ODC/ODP yang dipantau kapasitasnya."
                :href="route('network.nodes.index')" :accent="$ports['red_zone_count'] > 0 ? 'warning' : null">
                <x-dashboard.module-row label="Port terpakai" :value="number_format($ports['used_ports']) . ' / ' . number_format($ports['total_ports'])" />
                <x-dashboard.module-row label="Tingkat keterisian" :value="$pct($ports['occupancy_rate'])"
                    :tone="($ports['occupancy_rate'] ?? 0) >= 85 ? 'warning' : 'default'" />
                <x-dashboard.module-row label="Titik hampir penuh" :value="number_format($ports['red_zone_count']) . ' dari ' . number_format($ports['total_nodes'])"
                    :tone="$ports['red_zone_count'] > 0 ? 'danger' : 'default'" />
            </x-dashboard.module-card>
        @endisset

        @if(isset($modules['cable_crossings']) || isset($modules['material_inspections_pending']))
            <x-dashboard.module-card title="Kabel & Material Bongkaran" subtitle="Kondisi saat ini.">
                @isset($modules['cable_crossings'])
                    <x-dashboard.module-row label="Kabel jalan di pelanggan putus, belum ditarik" :value="number_format($modules['cable_crossings']['pending_removal'])"
                        :href="route('customer-cable-crossings.index')"
                        :tone="$modules['cable_crossings']['pending_removal'] > 0 ? 'warning' : 'default'" />
                    <x-dashboard.module-row label="Kabel tidak bisa ditarik" :value="number_format($modules['cable_crossings']['cannot_remove'])" />
                @endisset
                @isset($modules['material_inspections_pending'])
                    <x-dashboard.module-row label="Material bongkaran belum dicek gudang" :value="number_format($modules['material_inspections_pending'])"
                        :href="route('warehouse.infrastructure-recoveries.index')"
                        :tone="$modules['material_inspections_pending'] > 0 ? 'warning' : 'default'" />
                @endisset
            </x-dashboard.module-card>
        @endif
    </div>

    {{-- ── ROW 4: PELANGGAN & GUDANG ──────────────────────────────────── --}}
    <div x-show="activeTab === 'all' || activeTab === 'crm_warehouse'" x-transition class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <x-dashboard.panel title="Pelanggan per Cabang & Kelengkapan Data"
            subtitle="Jumlah pelanggan di tiap cabang, dan berapa yang datanya sudah lengkap sehingga bisa ditagih."
            :href="auth()->user()->hasPermission('customers.view') ? route('customers.index') : null" linkLabel="Kelola Data">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.07em] text-text-muted mb-2">Pelanggan per Cabang</p>
                    <div class="h-52 relative">
                        <canvas id="chart-pop-distribution"></canvas>
                    </div>
                </div>
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-[0.07em] text-text-muted mb-2">Status Kelengkapan Data</p>
                    <div class="h-52 relative flex items-center justify-center">
                        <canvas id="chart-customer-completeness"></canvas>
                    </div>
                </div>
            </div>

            <div class="-mx-5 -mb-5 mt-5 border-t border-border">
                <div class="flex items-center justify-between px-5 py-3">
                    <span class="text-[10px] font-semibold uppercase tracking-[0.07em] text-text-muted">Data Pelanggan yang Belum Lengkap</span>
                    @if(auth()->user()->hasPermission('customers.view'))
                        <a href="{{ route('customers.index', ['completeness_status' => 'perlu_dilengkapi']) }}" class="text-[11px] font-semibold uppercase tracking-[0.04em] text-primary hover:underline">Lihat Semua &rarr;</a>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[13px]">
                        <thead class="{{ $theadClass }}">
                            <tr>
                                <th class="{{ $thClass }}">ID / Nama</th>
                                <th class="{{ $thClass }}">Cabang</th>
                                <th class="{{ $thClass }}">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($incompleteCustomers->take(5) as $cust)
                                <tr class="hover:bg-surface-muted/50">
                                    <td class="px-5 py-2.5">
                                        <span class="font-mono font-semibold text-primary block">{{ $cust->customer_code }}</span>
                                        <span class="text-xs text-text-muted">{{ $cust->full_name }}</span>
                                    </td>
                                    <td class="px-5 py-2.5 text-text-secondary">{{ $cust->pop?->name ?? '-' }}</td>
                                    <td class="px-5 py-2.5">
                                        <x-ui.badge variant="warning">{{ $statusLabels[$cust->data_completeness_status] ?? $cust->data_completeness_status }}</x-ui.badge>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-4 text-center text-text-muted">Semua data pelanggan lengkap.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-dashboard.panel>

        <x-dashboard.panel title="Stok Gudang & Tagihan Paling Lama"
            subtitle="Barang yang sedang dikirim, dibawa teknisi, atau hampir habis, plus tagihan yang paling lama belum dibayar."
            :href="$canViewWarehouse ? route('warehouse.index') : null" linkLabel="Gudang">

            @if($canViewWarehouse)
                <x-dashboard.stat-strip cols="3">
                    <x-dashboard.stat label="Sedang Dikirim" :value="number_format($stats['warehouse_asset_overview']['in_transit_count'] ?? 0)" tone="primary" sub="Dalam perjalanan ke cabang" />
                    <x-dashboard.stat label="Dibawa Teknisi" :value="number_format($stats['warehouse_asset_overview']['custody_count'] ?? 0)" sub="Barang di lapangan" />
                    <x-dashboard.stat label="Kembali / Rusak" :value="number_format($stats['warehouse_asset_overview']['damaged_returned_count'] ?? 0)"
                        :tone="($stats['warehouse_asset_overview']['damaged_returned_count'] ?? 0) > 0 ? 'danger' : 'default'" sub="Perlu dicek" />
                </x-dashboard.stat-strip>

                {{-- Antrean Permintaan Gudang (Fase 2, ADHOC-313) --}}
                @if(isset($modules['stock_requests_open']) || isset($modules['pengajuan_barang_pending']))
                    <x-dashboard.stat-strip cols="2" class="border-t-0">
                        @isset($modules['stock_requests_open'])
                            <x-dashboard.stat label="Permintaan Stok Cabang" :value="number_format($modules['stock_requests_open'])"
                                :href="route('warehouse.stock-requests.index')" sub="Belum terpenuhi"
                                :tone="$modules['stock_requests_open'] > 0 ? 'warning' : 'default'" />
                        @endisset
                        @isset($modules['pengajuan_barang_pending'])
                            <x-dashboard.stat label="Pengajuan Barang" :value="number_format($modules['pengajuan_barang_pending'])"
                                :href="route('warehouse.pengajuan-barang.index')" sub="Menunggu keputusan FOP"
                                :tone="$modules['pengajuan_barang_pending'] > 0 ? 'warning' : 'default'" />
                        @endisset
                    </x-dashboard.stat-strip>
                @endif

                <div class="-mx-5">
                    <div class="flex items-center justify-between px-5 py-3">
                        <span class="text-[10px] font-semibold uppercase tracking-[0.07em] text-text-muted">Barang Hampir Habis di Cabang</span>
                        <x-ui.badge :variant="$lowStockItems->count() > 0 ? 'error' : 'neutral'">{{ $lowStockItems->count() }} barang</x-ui.badge>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-[13px]">
                            <thead class="{{ $theadClass }}">
                                <tr>
                                    <th class="{{ $thClass }}">Cabang</th>
                                    <th class="{{ $thClass }}">Nama Barang</th>
                                    <th class="{{ $thClass }} text-right">Sisa Stok</th>
                                    <th class="{{ $thClass }} text-right">Batas Minimum</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @forelse($lowStockItems->take(5) as $bal)
                                    <tr class="hover:bg-surface-muted/50">
                                        <td class="px-5 py-2.5 text-text-muted">{{ $bal->pop?->name ?? '-' }}</td>
                                        <td class="px-5 py-2.5 text-text-main">{{ $bal->item?->name ?? '-' }}</td>
                                        <td class="px-5 py-2.5 text-right font-mono font-semibold text-rose-700 dark:text-rose-300">{{ number_format($bal->qty, 0) }} {{ $bal->item?->unit }}</td>
                                        <td class="px-5 py-2.5 text-right font-mono text-text-muted">{{ number_format($bal->minimum_stock, 0) }} {{ $bal->item?->unit }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-5 py-4 text-center text-text-muted">Semua stok di atas batas minimum.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="-mx-5 -mb-5 {{ $canViewWarehouse ? 'border-t border-border' : '' }}">
                <div class="flex items-center justify-between px-5 py-3">
                    <span class="text-[10px] font-semibold uppercase tracking-[0.07em] text-text-muted">Tagihan Paling Lama Belum Lunas</span>
                    @if(auth()->user()->hasPermission('invoices.view'))
                        <a href="{{ route('invoices.index') }}" class="text-[11px] font-semibold uppercase tracking-[0.04em] text-primary hover:underline">Buka Tagihan &rarr;</a>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[13px]">
                        <thead class="{{ $theadClass }}">
                            <tr>
                                <th class="{{ $thClass }}">No. Tagihan / Pelanggan</th>
                                <th class="{{ $thClass }}">Periode</th>
                                <th class="{{ $thClass }} text-right">Sisa Tagihan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($dueInvoices->take(5) as $inv)
                                <tr class="hover:bg-surface-muted/50">
                                    <td class="px-5 py-2.5">
                                        <span class="font-mono font-semibold text-primary block">{{ $inv->invoice_number }}</span>
                                        <span class="text-xs text-text-muted">{{ $inv->customer?->full_name ?? '-' }} ({{ $inv->pop?->name ?? '-' }})</span>
                                    </td>
                                    <td class="px-5 py-2.5 font-mono font-semibold text-rose-700 dark:text-rose-300">{{ $inv->billing_period }}</td>
                                    <td class="px-5 py-2.5 text-right font-mono font-semibold text-text-main">{{ $currency($inv->remaining_amount) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-4 text-center text-text-muted">Tidak ada tagihan yang terlambat.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </x-dashboard.panel>
    </div>

</div>

{{-- Data JSON Payloads untuk Chart.js --}}
<script type="application/json" id="data-financial-trend">{!! json_encode($stats['financial_trend'] ?? ['labels' => [], 'invoices' => [], 'payments' => []]) !!}</script>
<script type="application/json" id="data-payment-channels">{!! json_encode($stats['payment_channels'] ?? []) !!}</script>
<script type="application/json" id="data-aging-receivables">{!! json_encode($stats['aging_receivables'] ?? []) !!}</script>
<script type="application/json" id="data-top-issues">{!! json_encode($topIssueCategories->map(fn($row) => ['name' => $row->issueCategory?->name ?? 'Lainnya', 'total' => (int) $row->total])) !!}</script>
<script type="application/json" id="data-pop-distribution">{!! json_encode($customersByPopChartData ?? ['labels' => [], 'data' => []]) !!}</script>
<script type="application/json" id="data-customer-completeness">{!! json_encode($stats['customer_completeness'] ?? ['draft' => 0, 'perlu_dilengkapi' => 0, 'lengkap' => 0, 'siap_billing' => 0]) !!}</script>

@endsection

@section('scripts')
@vite(['resources/js/charts.js'])
<script>
(function () {
    const isDarkMode = document.documentElement.classList.contains('dark');

    // Warna grafik dibaca dari token CSS (resources/css/app.css), bukan hex
    // hardcode — supaya ikut tema gelap/terang dan gak menyimpang dari
    // Design.md kalau token diganti. Fallback hex cuma jaga-jaga token kosong.
    const rootStyle = getComputedStyle(document.documentElement);
    const token = (name, fallback) => (rootStyle.getPropertyValue(name) || '').trim() || fallback;

    const color = {
        primary: token('--color-primary', '#0284C7'),
        info: token('--color-info', '#2563EB'),
        success: token('--color-success', '#10B981'),
        warning: token('--color-warning', '#B45309'),
        error: token('--color-error', '#EF4444'),
        neutral: token('--color-text-disabled', '#94A3B8'),
        surface: token('--color-surface', '#FFFFFF'),
        text: token('--color-text-secondary', '#334155'),
        textMuted: token('--color-text-muted', '#64748B'),
    };
    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.06)' : 'rgba(15, 23, 42, 0.06)';
    const fontUi = { size: 11, family: 'Inter' };
    const fontData = { size: 10, family: 'JetBrains Mono' };
    const rupiah = (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v);
    const rupiahShort = (v) => 'Rp ' + (v >= 1000000 ? (v / 1000000) + 'Jt' : (v / 1000) + 'Rb');

    function readJson(id) {
        const el = document.getElementById(id);
        if (!el) return null;
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return null;
        }
    }

    function horizontalBarOptions(tooltipLabel, xTicks) {
        return {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: tooltipLabel } },
            },
            scales: {
                x: { grid: { color: gridColor }, ticks: Object.assign({ color: color.textMuted, font: fontData }, xTicks) },
                y: { grid: { display: false }, ticks: { color: color.text, font: fontUi } },
            },
        };
    }

    function doughnutOptions(withMoneyTooltip) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, color: color.text, font: fontUi } },
                tooltip: withMoneyTooltip ? { callbacks: { label: (ctx) => ctx.label + ': ' + rupiah(ctx.raw) } } : {},
            },
        };
    }

    function renderDashboardCharts() {
        if (typeof window.Chart === 'undefined') {
            return;
        }

        // 1. Tren tagihan vs uang masuk
        const finTrendData = readJson('data-financial-trend');
        const finTrendCanvas = document.getElementById('chart-financial-trend');
        if (finTrendData && finTrendCanvas && finTrendData.labels.length > 0 && !finTrendCanvas._chartInstance) {
            finTrendCanvas._chartInstance = new window.Chart(finTrendCanvas, {
                type: 'bar',
                data: {
                    labels: finTrendData.labels,
                    datasets: [
                        { label: 'Total Tagihan', data: finTrendData.invoices, backgroundColor: color.primary, borderRadius: 4, maxBarThickness: 32 },
                        { label: 'Uang Masuk', data: finTrendData.payments, backgroundColor: color.success, borderRadius: 4, maxBarThickness: 32 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + rupiah(ctx.raw) } },
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: color.textMuted, font: fontUi } },
                        y: { grid: { color: gridColor }, ticks: { color: color.textMuted, font: fontData, callback: rupiahShort } },
                    },
                },
            });
        }

        // 2. Kanal pembayaran (donut) — palet token, tanpa indigo/violet (Design.md §21)
        const paymentChannelsData = readJson('data-payment-channels');
        const paymentChannelsCanvas = document.getElementById('chart-payment-channels');
        if (paymentChannelsData && paymentChannelsCanvas && !paymentChannelsCanvas._chartInstance) {
            const items = Object.values(paymentChannelsData);
            paymentChannelsCanvas._chartInstance = new window.Chart(paymentChannelsCanvas, {
                type: 'doughnut',
                data: {
                    labels: items.map((i) => i.label),
                    datasets: [{
                        data: items.map((i) => i.amount),
                        backgroundColor: [color.primary, color.info, color.success, color.warning, color.neutral],
                        borderWidth: 2,
                        borderColor: color.surface,
                    }],
                },
                options: doughnutOptions(true),
            });
        }

        // 3. Umur piutang — hijau → kuning → merah sesuai tingkat bahaya
        const agingData = readJson('data-aging-receivables');
        const agingCanvas = document.getElementById('chart-aging-receivables');
        if (agingData && agingCanvas && !agingCanvas._chartInstance) {
            agingCanvas._chartInstance = new window.Chart(agingCanvas, {
                type: 'bar',
                data: {
                    labels: ['Belum jatuh tempo', 'Telat 1–30 hari', 'Telat 31–60 hari', 'Telat >60 hari'],
                    datasets: [{
                        data: [
                            agingData.lancar?.amount ?? 0,
                            agingData.days_1_30?.amount ?? 0,
                            agingData.days_31_60?.amount ?? 0,
                            agingData.days_over_60?.amount ?? 0,
                        ],
                        backgroundColor: [color.success, color.warning, color.warning, color.error],
                        borderRadius: 4,
                        maxBarThickness: 20,
                    }],
                },
                options: horizontalBarOptions((ctx) => rupiah(ctx.raw), { callback: (v) => 'Rp ' + (v >= 1000000 ? (v / 1000000) + 'Jt' : v) }),
            });
        }

        // 4. Top kategori gangguan — jumlah tiket, bukan status error → warna primary
        const issuesData = readJson('data-top-issues');
        const issuesCanvas = document.getElementById('chart-top-issues');
        if (issuesData && issuesCanvas && issuesData.length > 0 && !issuesCanvas._chartInstance) {
            issuesCanvas._chartInstance = new window.Chart(issuesCanvas, {
                type: 'bar',
                data: {
                    labels: issuesData.map((i) => i.name),
                    datasets: [{ data: issuesData.map((i) => i.total), backgroundColor: color.primary, borderRadius: 4, maxBarThickness: 16 }],
                },
                options: horizontalBarOptions((ctx) => ctx.raw + ' Tiket', { precision: 0 }),
            });
        }

        // 5. Sebaran pelanggan per cabang
        const popData = readJson('data-pop-distribution');
        const popCanvas = document.getElementById('chart-pop-distribution');
        if (popData && popCanvas && popData.labels.length > 0 && !popCanvas._chartInstance) {
            popCanvas._chartInstance = new window.Chart(popCanvas, {
                type: 'bar',
                data: {
                    labels: popData.labels,
                    datasets: [{ data: popData.data, backgroundColor: color.primary, borderRadius: 4, maxBarThickness: 18 }],
                },
                options: horizontalBarOptions((ctx) => ctx.raw + ' Pelanggan', { precision: 0 }),
            });
        }

        // 6. Kelengkapan data pelanggan (donut)
        const compData = readJson('data-customer-completeness');
        const compCanvas = document.getElementById('chart-customer-completeness');
        if (compData && compCanvas && !compCanvas._chartInstance) {
            compCanvas._chartInstance = new window.Chart(compCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Siap Billing', 'Lengkap', 'Perlu Dilengkapi', 'Draft'],
                    datasets: [{
                        data: [compData.siap_billing ?? 0, compData.lengkap ?? 0, compData.perlu_dilengkapi ?? 0, compData.draft ?? 0],
                        backgroundColor: [color.success, color.primary, color.warning, color.neutral],
                        borderWidth: 2,
                        borderColor: color.surface,
                    }],
                },
                options: doughnutOptions(false),
            });
        }
    }

    if (window.Chart) {
        renderDashboardCharts();
    } else {
        window.addEventListener('chart:ready', renderDashboardCharts, { once: true });
    }
    document.addEventListener('DOMContentLoaded', renderDashboardCharts);
})();
</script>
@endsection
