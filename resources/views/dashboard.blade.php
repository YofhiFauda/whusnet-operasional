@extends('layouts.app')

@section('title', 'Dashboard - Whusnet Operasional')
@section('page_title', 'Dashboard')

@section('content')
@php
    $currency = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $percent = fn ($value, $total) => number_format(((int) $value / max(1, (int) $total)) * 100, 1) . '%';
    $statusLabels = [
        'draft' => 'Draft',
        'perlu_dilengkapi' => 'Perlu Dilengkapi',
        'lengkap' => 'Lengkap',
        'siap_billing' => 'Siap Billing',
        'belum_dibayar' => 'Belum Dibayar',
        'sebagian' => 'Dibayar Sebagian',
        'lunas' => 'Lunas',
        'batal' => 'Batal',
        'tak_tertagih' => 'Tak Tertagih',
    ];
@endphp

<div class="space-y-6">
    <!-- Filter Panel (Naked, following Design.md §1.5) -->
    <form method="GET" action="{{ route('dashboard') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end mb-6">
        <div>
            <label for="pop_id" class="block text-xs font-semibold text-text-secondary mb-1.5">Filter POP</label>
            <x-ui.select name="pop_id" id="pop_id">
                <option value="">Semua POP yang dapat diakses</option>
                @foreach($pops as $pop)
                    <option value="{{ $pop->id }}" @selected((string) $filters['pop_id'] === (string) $pop->id)>
                        {{ $pop->name }}
                    </option>
                @endforeach
            </x-ui.select>
        </div>

        <div>
            <label for="period_from" class="block text-xs font-semibold text-text-secondary mb-1.5">Periode Dari</label>
            <x-ui.input type="month" name="period_from" id="period_from" value="{{ $filters['period_from'] }}" />
        </div>

        <div>
            <label for="period_to" class="block text-xs font-semibold text-text-secondary mb-1.5">Periode Sampai</label>
            <x-ui.input type="month" name="period_to" id="period_to" value="{{ $filters['period_to'] }}" />
        </div>

        <div class="flex gap-2">
            <x-ui.button type="submit" variant="primary" class="w-full md:w-auto">
                Terapkan Filter
            </x-ui.button>
            <x-ui.button type="button" variant="secondary" tag="a" href="{{ route('dashboard') }}" class="w-full md:w-auto text-center">
                Reset
            </x-ui.button>
        </div>
    </form>

    <!-- Summary Cards Grid (Metric Cards, following Design.md §1.3 & §5) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Total Pelanggan (Metric Card) -->
        <div class="metric-card">
            <div>
                <div class="metric-card-label">
                    <span>Total Pelanggan</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value">{{ number_format($stats['total_customers']) }}</p>
                </div>
            </div>
            <p class="metric-card-footer">Sesuai filter POP</p>
        </div>

        <!-- Pelanggan Aktif (Metric Card - Success) -->
        <div class="metric-card status-success">
            <div>
                <div class="metric-card-label">
                    <span>Pelanggan Aktif</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value">{{ number_format($stats['active_customers']) }}</p>
                </div>
            </div>
            <p class="metric-card-footer"><span class="font-mono">{{ $percent($stats['active_customers'], $stats['total_customers']) }}</span> dari total pelanggan</p>
        </div>

        <!-- Data Belum Lengkap (Operational Status Card - Warning) -->
        <div class="metric-card status-warning">
            <div>
                <div class="metric-card-label">
                    <span>Data Belum Lengkap</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value">{{ number_format($stats['incomplete_customers']) }}</p>
                </div>
            </div>
            <p class="metric-card-footer"><span class="font-mono">{{ $percent($stats['incomplete_customers'], $stats['total_customers']) }}</span> perlu dilengkapi</p>
        </div>

        <!-- Siap Billing (Metric Card - Info) -->
        <div class="metric-card status-info">
            <div>
                <div class="metric-card-label">
                    <span>Siap Billing</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value">{{ number_format($stats['ready_billing_customers']) }}</p>
                </div>
            </div>
            <p class="metric-card-footer"><span class="font-mono">{{ $percent($stats['ready_billing_customers'], $stats['total_customers']) }}</span> siap ditagih</p>
        </div>

        <!-- Tagihan Periode (Metric Card) -->
        <div class="metric-card">
            <div>
                <div class="metric-card-label">
                    <span>Tagihan Periode ({{ $filters['period_label'] }})</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value text-2xl">{{ $currency($stats['total_invoices_amount']) }}</p>
                </div>
            </div>
            <p class="metric-card-footer">Berdasarkan periode tagihan</p>
        </div>

        <!-- Pembayaran Periode (Metric Card - Success) -->
        <div class="metric-card status-success">
            <div>
                <div class="metric-card-label">
                    <span>Pembayaran Periode ({{ $filters['period_label'] }})</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value text-2xl">{{ $currency($stats['total_payments_amount']) }}</p>
                </div>
            </div>
            <p class="metric-card-footer">Hanya pembayaran valid</p>
        </div>

        <!-- Total Tunggakan (Operational Status Card - Danger) -->
        <div class="metric-card status-error">
            <div>
                <div class="metric-card-label">
                    <span>Total Tunggakan</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value text-2xl">{{ $currency($stats['total_unpaid_amount']) }}</p>
                </div>
            </div>
            <p class="metric-card-footer">Sisa piutang periode sebelum bulan ini</p>
        </div>

        <!-- Piutang Tagihan (Operational Status Card - Danger) -->
        <div class="metric-card status-error">
            <div>
                <div class="metric-card-label">
                    <span>Piutang Tagihan</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value">{{ number_format($stats['due_invoices_count']) }}</p>
                </div>
            </div>
            <p class="metric-card-footer">Belum lunas dari periode sebelum bulan ini</p>
        </div>

        <!-- Net Customer Growth (KPI Strip — Pilar 1) -->
        <div class="metric-card {{ $stats['net_customer_growth'] >= 0 ? 'status-success' : 'status-error' }}">
            <div>
                <div class="metric-card-label">
                    <span>Net Customer Growth</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value">{{ $stats['net_customer_growth'] >= 0 ? '+' : '' }}{{ number_format($stats['net_customer_growth']) }}</p>
                </div>
            </div>
            <p class="metric-card-footer">{{ number_format($stats['new_active_customers']) }} aktif baru &middot; {{ number_format($stats['terminated_customers']) }} putus</p>
        </div>

        {{-- Omset per Segmen (Sales/Teknisi/Bisnis) — REKAP PERUSAHAAN, bukan
             omset pribadi. Sebelumnya tanpa gerbang permission sama sekali:
             role `sales` biasa (tanpa `sales_omset_dashboard.view`) ikut
             melihat omset SELURUH sales lain di perusahaan lewat dashboard —
             bertentangan sama aturan "Sales tak boleh akses laporan
             keuangan". Gerbang sama persis dengan link sidebar "Omset Sales". --}}
        @can('sales_omset_dashboard.view')
        <!-- Omset Sales (Metric Card) -->
        <div class="metric-card">
            <div>
                <div class="metric-card-label flex items-center justify-between">
                    <span>Omset Sales</span>
                    <span class="px-2 py-0.5 text-[10px] font-semibold bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-300 rounded-full">Sales</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value text-2xl text-sky-600 dark:text-sky-400">{{ $currency($stats['omset_sales_amount']) }}</p>
                </div>
            </div>
            <p class="metric-card-footer"><span class="font-mono">{{ number_format($stats['omset_sales_count']) }}</span> pelanggan aktif dari Sales</p>
        </div>

        <!-- Omset Teknisi (Metric Card) -->
        <div class="metric-card">
            <div>
                <div class="metric-card-label flex items-center justify-between">
                    <span>Omset Teknisi</span>
                    <span class="px-2 py-0.5 text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 rounded-full">Teknisi</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value text-2xl text-emerald-600 dark:text-emerald-400">{{ $currency($stats['omset_teknisi_amount']) }}</p>
                </div>
            </div>
            <p class="metric-card-footer"><span class="font-mono">{{ number_format($stats['omset_teknisi_count']) }}</span> pelanggan aktif dari Teknisi</p>
        </div>

        <!-- Omset Bisnis (Metric Card) -->
        <div class="metric-card">
            <div>
                <div class="metric-card-label flex items-center justify-between">
                    <span>Omset Bisnis</span>
                    <span class="px-2 py-0.5 text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 rounded-full">Bisnis</span>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value text-2xl text-amber-600 dark:text-amber-400">{{ $currency($stats['omset_bisnis_amount']) }}</p>
                </div>
            </div>
            <p class="metric-card-footer"><span class="font-mono">{{ number_format($stats['omset_bisnis_count']) }}</span> pelanggan aktif segmen Bisnis</p>
        </div>
        @endcan
    </div>

    @if($canViewCash)
    <!-- Posisi Keuangan & Arus Kas (Pilar 2) -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <x-ui.card class="p-5 xl:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-text-main">Posisi Keuangan & Arus Kas</h3>
                <div class="flex items-center gap-2">
                    @if($stats['cash_deposit_open_difference_count'] > 0)
                        <x-ui.badge variant="error">{{ $stats['cash_deposit_open_difference_count'] }} selisih admin</x-ui.badge>
                    @endif
                    @if($stats['collector_shortfall_count'] > 0)
                        <x-ui.badge variant="error">{{ $stats['collector_shortfall_count'] }} kurang setor kolektor</x-ui.badge>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="rounded-md border border-border p-3">
                    <p class="text-xs text-text-muted mb-1">Kas Mengendap di Kasir POP</p>
                    <p class="text-lg font-semibold text-text-main font-mono">{{ $currency($stats['cash_at_office_amount']) }}</p>
                </div>
                <div class="rounded-md border border-border p-3">
                    <p class="text-xs text-text-muted mb-1">Uang di Tangan Kolektor</p>
                    <p class="text-lg font-semibold text-text-main font-mono">{{ $currency($stats['cash_with_collector_amount']) }}</p>
                </div>
                <div class="rounded-md border border-border p-3">
                    <p class="text-xs text-text-muted mb-1">Setoran Menunggu Verifikasi</p>
                    <p class="text-lg font-semibold text-text-main font-mono">{{ $currency($stats['cash_deposit_pending_amount']) }}</p>
                    <p class="text-xs text-text-muted mt-0.5">{{ number_format($stats['cash_deposit_pending_count']) }} setoran</p>
                </div>
                <div class="rounded-md border p-3 {{ $stats['collector_shortfall_count'] > 0 ? 'border-error-border' : 'border-border' }}">
                    <p class="text-xs text-text-muted mb-1">Kurang Setor Kolektor</p>
                    <p class="text-lg font-semibold {{ $stats['collector_shortfall_count'] > 0 ? 'text-error' : 'text-text-main' }} font-mono">{{ $currency($stats['collector_shortfall_amount']) }}</p>
                    <p class="text-xs text-text-muted mt-0.5">{{ number_format($stats['collector_shortfall_count']) }} kolektor</p>
                </div>
            </div>
        </x-ui.card>

        <!-- Action Center: Setoran Kas Pending (Pilar 6) -->
        <x-ui.card class="p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-text-main">Butuh Verifikasi</h3>
                @if(auth()->user()->hasPermission('cash_deposit.view'))
                    <x-ui.link href="{{ route('cash-deposits.index') }}" class="text-xs">Lihat Semua</x-ui.link>
                @endif
            </div>

            <div class="space-y-2">
                @forelse($pendingCashDeposits as $deposit)
                    <a href="{{ route('cash-deposits.index') }}" class="flex items-center justify-between rounded-md border border-border p-2.5 text-sm hover:bg-surface-muted hover:border-primary-border transition-all duration-150">
                        <span>
                            <span class="block font-medium text-text-main">{{ $deposit->pop?->name ?? '-' }}</span>
                            <span class="block text-xs text-text-muted">{{ $deposit->depositor?->name ?? '-' }}</span>
                        </span>
                        <span class="font-mono font-semibold text-text-main">{{ $currency($deposit->declared_amount) }}</span>
                    </a>
                @empty
                    <p class="text-sm text-text-muted">Tidak ada setoran menunggu verifikasi.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>
    @endif

    <!-- Funnel Akuisisi & Retensi Pelanggan (Pilar 3) -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <x-ui.card class="p-5 xl:col-span-2">
            <h3 class="text-sm font-semibold text-text-main mb-4">Funnel Akuisisi Pelanggan Baru</h3>

            <div class="flex flex-col sm:flex-row items-stretch gap-2">
                @foreach([
                    ['label' => 'Menunggu Survey', 'value' => $stats['funnel_waiting_survey']],
                    ['label' => 'Menunggu ACC Admin', 'value' => $stats['funnel_waiting_acc']],
                    ['label' => 'Proses Pemasangan', 'value' => $stats['funnel_waiting_installation']],
                    ['label' => 'Menunggu Verifikasi Admin', 'value' => $stats['funnel_verification_admin']],
                ] as $index => $stage)
                    <div class="flex-1 rounded-md border border-border p-3 text-center">
                        <p class="text-xs text-text-muted mb-1">{{ $index + 1 }}. {{ $stage['label'] }}</p>
                        <p class="text-xl font-semibold text-text-main font-mono">{{ number_format($stage['value']) }}</p>
                    </div>
                    @if(!$loop->last)
                        <div class="hidden sm:flex items-center justify-center text-text-disabled">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    @endif
                @endforeach
            </div>
            <p class="text-xs text-text-muted mt-3">{{ number_format($stats['rejected_customers']) }} pelanggan gagal/batal pada periode {{ $filters['period_label'] }}.</p>
        </x-ui.card>

        <!-- Retensi & Status Pelanggan (Metric Card kecil) -->
        <x-ui.card class="p-5">
            <h3 class="text-sm font-semibold text-text-main mb-4">Retensi & Status Pelanggan</h3>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-md border border-border p-3">
                    <p class="text-xs text-text-muted mb-1">Aktif</p>
                    <p class="text-lg font-semibold text-success font-mono">{{ number_format($stats['active_customers']) }}</p>
                </div>
                <div class="rounded-md border border-border p-3">
                    <p class="text-xs text-text-muted mb-1">Isolir</p>
                    <p class="text-lg font-semibold text-warning font-mono">{{ number_format($stats['suspended_customers']) }}</p>
                </div>
                <div class="rounded-md border border-border p-3">
                    <p class="text-xs text-text-muted mb-1">Putus (Periode Ini)</p>
                    <p class="text-lg font-semibold text-error font-mono">{{ number_format($stats['terminated_customers']) }}</p>
                </div>
                <div class="rounded-md border border-border p-3">
                    <p class="text-xs text-text-muted mb-1">Siap Tagih</p>
                    <p class="text-lg font-semibold text-text-main font-mono">{{ number_format($stats['ready_billing_customers']) }}</p>
                </div>
            </div>
        </x-ui.card>
    </div>

    @if($canViewTickets)
    <!-- Kualitas Layanan & Gangguan / Ticketing SLA (Pilar 4) -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <x-ui.card class="p-5 xl:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-text-main">Kualitas Layanan & Gangguan (Ticketing)</h3>
                @if($stats['ticket_sla_breach_count'] > 0)
                    <x-ui.badge variant="error">{{ $stats['ticket_sla_breach_count'] }} breach SLA</x-ui.badge>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                @if(auth()->user()->hasPermission('noc_worksheet.view'))
                    <a href="{{ route('noc.worksheet') }}" class="rounded-md border border-border p-3 text-center hover:bg-surface-muted hover:border-primary-border transition-all duration-150">
                        <p class="text-xs text-text-muted mb-1">Masuk</p>
                        <p class="text-lg font-semibold text-text-main font-mono">{{ number_format($stats['ticket_bucket_masuk']) }}</p>
                    </a>
                    <a href="{{ route('noc.worksheet') }}" class="rounded-md border border-border p-3 text-center hover:bg-surface-muted hover:border-primary-border transition-all duration-150">
                        <p class="text-xs text-text-muted mb-1">Diproses</p>
                        <p class="text-lg font-semibold text-text-main font-mono">{{ number_format($stats['ticket_bucket_diproses']) }}</p>
                    </a>
                @else
                    <div class="rounded-md border border-border p-3 text-center">
                        <p class="text-xs text-text-muted mb-1">Masuk</p>
                        <p class="text-lg font-semibold text-text-main font-mono">{{ number_format($stats['ticket_bucket_masuk']) }}</p>
                    </div>
                    <div class="rounded-md border border-border p-3 text-center">
                        <p class="text-xs text-text-muted mb-1">Diproses</p>
                        <p class="text-lg font-semibold text-text-main font-mono">{{ number_format($stats['ticket_bucket_diproses']) }}</p>
                    </div>
                @endif

                @if(auth()->user()->hasPermission('tickets.selesai.view'))
                    <a href="{{ route('tickets.selesai') }}" class="rounded-md border border-border p-3 text-center hover:bg-surface-muted hover:border-primary-border transition-all duration-150">
                        <p class="text-xs text-text-muted mb-1">Selesai</p>
                        <p class="text-lg font-semibold text-success font-mono">{{ number_format($stats['ticket_bucket_selesai']) }}</p>
                    </a>
                @else
                    <div class="rounded-md border border-border p-3 text-center">
                        <p class="text-xs text-text-muted mb-1">Selesai</p>
                        <p class="text-lg font-semibold text-success font-mono">{{ number_format($stats['ticket_bucket_selesai']) }}</p>
                    </div>
                @endif

                @if(auth()->user()->hasPermission('tickets.dibatalkan.view'))
                    <a href="{{ route('tickets.dibatalkan') }}" class="rounded-md border border-border p-3 text-center hover:bg-surface-muted hover:border-primary-border transition-all duration-150">
                        <p class="text-xs text-text-muted mb-1">Dibatalkan</p>
                        <p class="text-lg font-semibold text-text-muted font-mono">{{ number_format($stats['ticket_bucket_dibatalkan']) }}</p>
                    </a>
                @else
                    <div class="rounded-md border border-border p-3 text-center">
                        <p class="text-xs text-text-muted mb-1">Dibatalkan</p>
                        <p class="text-lg font-semibold text-text-muted font-mono">{{ number_format($stats['ticket_bucket_dibatalkan']) }}</p>
                    </div>
                @endif
            </div>
        </x-ui.card>

        <!-- Top 5 Kategori Gangguan -->
        <x-ui.card class="p-5">
            <h3 class="text-sm font-semibold text-text-main mb-4">Top Kategori Gangguan ({{ $filters['period_label'] }})</h3>
            <div class="space-y-3">
                @forelse($topIssueCategories as $row)
                    <div>
                        <div class="flex justify-between gap-4 text-sm">
                            <span class="font-medium text-text-secondary">{{ $row->issueCategory?->name ?? 'Tanpa Kategori' }}</span>
                            <span class="font-semibold text-text-main font-mono">{{ number_format($row->total) }}</span>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-surface-muted">
                            <div class="h-2 rounded-full bg-primary" style="width: {{ min(100, ((int) $row->total / max(1, (int) $topIssueCategories->max('total'))) * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-text-muted">Tidak ada tiket dengan kategori pada periode ini.</p>
                @endforelse
            </div>
        </x-ui.card>
    </div>
    @endif

    @if($canViewFopDelivery)
    <!-- Efisiensi Delivery Lapangan (Pilar 5) -->
    <x-ui.card class="p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-text-main">Efisiensi Delivery Lapangan ({{ $filters['period_label'] }})</h3>
            <x-ui.link href="{{ route('fop.dashboard') }}" class="text-xs">Lihat Dashboard FOP</x-ui.link>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="rounded-md border border-border p-3">
                <p class="text-xs text-text-muted mb-1">Survey Selesai</p>
                <p class="text-lg font-semibold text-text-main font-mono">{{ number_format($stats['fop_survey_completed_count']) }}</p>
            </div>
            <div class="rounded-md border border-border p-3">
                <p class="text-xs text-text-muted mb-1">PSB Selesai</p>
                <p class="text-lg font-semibold text-text-main font-mono">{{ number_format($stats['fop_psb_completed_count']) }}</p>
            </div>
            <div class="rounded-md border border-border p-3">
                <p class="text-xs text-text-muted mb-1">Rata-rata Lead Time PSB</p>
                <p class="text-lg font-semibold text-text-main font-mono">{{ is_null($stats['fop_psb_avg_lead_time_days']) ? '-' : $stats['fop_psb_avg_lead_time_days'].' hari' }}</p>
            </div>
            <div class="rounded-md border p-3 {{ $stats['fop_overdue_survey_count'] + $stats['fop_overdue_psb_count'] > 0 ? 'border-error-border' : 'border-border' }}">
                <p class="text-xs text-text-muted mb-1">Overdue Antrean</p>
                <p class="text-lg font-semibold {{ $stats['fop_overdue_survey_count'] + $stats['fop_overdue_psb_count'] > 0 ? 'text-error' : 'text-text-main' }} font-mono">
                    {{ number_format($stats['fop_overdue_survey_count']) }} survey &middot; {{ number_format($stats['fop_overdue_psb_count']) }} PSB
                </p>
            </div>
        </div>
    </x-ui.card>
    @endif

    @if($canViewWarehouse)
    <!-- Alert Stok Kritis POP (Pilar 6) -->
    <x-ui.card class="p-5">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-text-main">Alert Stok Kritis POP</h3>
            <div class="flex items-center gap-2">
                @if($stats['low_stock_count'] > 0)
                    <x-ui.badge variant="error">{{ $stats['low_stock_count'] }} item kritis</x-ui.badge>
                @endif
                <x-ui.link href="{{ route('warehouse.index') }}" class="text-xs">Lihat Semua</x-ui.link>
            </div>
        </div>

        <x-ui.table :headers="['POP', 'Barang', 'Sisa Stok', 'Batas Minimum']">
            @forelse($lowStockItems as $balance)
                <tr>
                    <td class="text-left text-text-muted">{{ $balance->pop?->name ?? '-' }}</td>
                    <td class="text-left font-medium text-text-main">{{ $balance->item?->name ?? '-' }}</td>
                    <td class="data-cell text-left text-error font-semibold">{{ number_format($balance->qty, 0) }} {{ $balance->item?->unit }}</td>
                    <td class="data-cell text-left text-text-muted">{{ number_format($balance->minimum_stock, 0) }} {{ $balance->item?->unit }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="py-6 text-center text-text-muted">Tidak ada stok di bawah batas minimum.</td>
                </tr>
            @endforelse
        </x-ui.table>
    </x-ui.card>
    @endif

    <!-- Details Section Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Customers by POP (Insight Card) -->
        <x-ui.card class="p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-text-main">Total Pelanggan per POP</h3>
                <x-ui.badge type="neutral" class="font-mono">{{ $customersByPop->count() }} POP</x-ui.badge>
            </div>

            <div class="space-y-3">
                @forelse($customersByPop as $row)
                    <div>
                        <div class="flex justify-between gap-4 text-sm">
                            <span class="font-medium text-text-secondary">{{ $row->pop?->name ?? 'Tanpa POP' }}</span>
                            <span class="font-semibold text-text-main font-mono">{{ number_format($row->total) }}</span>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-surface-muted">
                            <div class="h-2 rounded-full bg-sky-655 bg-primary" style="width: {{ min(100, ((int) $row->total / max(1, (int) $stats['total_customers'])) * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-text-muted">Belum ada pelanggan pada filter ini.</p>
                @endforelse
            </div>
        </x-ui.card>

        <!-- Due Invoices Table -->
        <x-ui.card class="p-5 xl:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-text-main">Piutang Tagihan</h3>
                @if(auth()->user()->hasPermission('view_invoices'))
                    <x-ui.link href="{{ route('invoices.index') }}" class="text-xs">Lihat Semua</x-ui.link>
                @endif
            </div>

            <x-ui.table :headers="['Invoice', 'Pelanggan', 'POP', 'Periode', 'Sisa Tagihan']">
                @forelse($dueInvoices as $invoice)
                    <tr>
                        <td class="data-cell text-left font-medium text-text-main">{{ $invoice->invoice_number }}</td>
                        <td class="text-left text-text-secondary">{{ $invoice->customer?->full_name ?? '-' }}</td>
                        <td class="text-left text-text-muted">{{ $invoice->pop?->name ?? '-' }}</td>
                        <td class="data-cell text-left text-error font-semibold">{{ $invoice->billing_period }}</td>
                        <td class="data-cell text-right font-semibold text-text-main">{{ $currency($invoice->remaining_amount) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-text-muted">Tidak ada piutang tagihan.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>
    </div>

    <!-- Incomplete Customers and Quick Access Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Incomplete Customers Table -->
        <x-ui.card class="p-5 xl:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-text-main">Pelanggan yang Perlu Dilengkapi</h3>
                @if(auth()->user()->hasPermission('view_customers'))
                    <x-ui.link href="{{ route('customers.index', ['completeness_status' => 'perlu_dilengkapi']) }}" class="text-xs">Lihat Semua</x-ui.link>
                @endif
            </div>

            <x-ui.table :headers="['ID Pelanggan', 'Nama', 'POP', 'Status Kelengkapan', 'Terakhir Diupdate']">
                @forelse($incompleteCustomers as $customer)
                    <tr>
                        <td class="data-cell text-left font-medium text-text-main">{{ $customer->customer_code }}</td>
                        <td class="text-left text-text-secondary">{{ $customer->full_name }}</td>
                        <td class="text-left text-text-muted">{{ $customer->pop?->name ?? '-' }}</td>
                        <td class="text-left">
                            <x-ui.badge type="warning">
                                {{ $statusLabels[$customer->data_completeness_status] ?? $customer->data_completeness_status }}
                            </x-ui.badge>
                        </td>
                        <td class="data-cell text-left text-text-muted">{{ optional($customer->updated_at)->format('d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-6 text-center text-text-muted">Tidak ada pelanggan yang perlu dilengkapi.</td>
                    </tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>

        <!-- Quick Access Card -->
        <x-ui.card class="p-5">
            <h3 class="text-sm font-semibold text-text-main mb-4">Akses Cepat</h3>
            <div class="space-y-3">
                @php $hasQuickAction = false; @endphp

                @if(auth()->user()->hasPermission('view_customers'))
                    @php $hasQuickAction = true; @endphp
                    <a href="{{ route('customers.index') }}" class="flex items-center justify-between rounded-md border border-border p-3 text-sm font-medium text-text-secondary hover:bg-surface-muted hover:border-primary-border hover:text-primary transition-all duration-150 cursor-pointer">
                        <span>Data Pelanggan</span>
                        <svg class="h-4 w-4 text-text-disabled hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif

                @if(auth()->user()->hasPermission('view_invoices'))
                    @php $hasQuickAction = true; @endphp
                    <a href="{{ route('invoices.index') }}" class="flex items-center justify-between rounded-md border border-border p-3 text-sm font-medium text-text-secondary hover:bg-surface-muted hover:border-primary-border hover:text-primary transition-all duration-150 cursor-pointer">
                        <span>Daftar Tagihan</span>
                        <svg class="h-4 w-4 text-text-disabled hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif

                @if(auth()->user()->hasPermission('view_payments'))
                    @php $hasQuickAction = true; @endphp
                    <a href="{{ route('payments.index') }}" class="flex items-center justify-between rounded-md border border-border p-3 text-sm font-medium text-text-secondary hover:bg-surface-muted hover:border-primary-border hover:text-primary transition-all duration-150 cursor-pointer">
                        <span>Riwayat Pembayaran</span>
                        <svg class="h-4 w-4 text-text-disabled hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif

                @if(!$hasQuickAction)
                    <p class="text-sm text-text-muted">Tidak ada akses cepat yang tersedia untuk peran Anda.</p>
                @endif

                <div class="mt-6 pt-4 border-t border-border">
                    <p class="text-xs font-semibold text-text-muted mb-3 uppercase tracking-wider">Uji Coba UI (Toast & Dialog)</p>
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <button type="button" onclick="Toast.success('Berhasil', 'Aksi berhasil dilakukan dengan aman.')" class="px-3 py-2 bg-success-bg text-success hover:opacity-90 border border-success-border rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Success</button>
                        <button type="button" onclick="Toast.error('Sistem Error', 'Terjadi kesalahan saat memproses data Anda. Silakan coba lagi nanti.')" class="px-3 py-2 bg-error-bg text-error hover:opacity-90 border border-error-border rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Error</button>
                        <button type="button" onclick="Toast.warning('Perhatian', 'Kuota penyimpanan Anda hampir penuh.')" class="px-3 py-2 bg-warning-bg text-warning hover:opacity-90 border border-warning-border rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Warning</button>
                        <button type="button" onclick="Toast.info('Pembaruan', 'Sistem akan melakukan maintenance pada pukul 00:00 WIB.')" class="px-3 py-2 bg-info-bg text-info hover:opacity-90 border border-info-border rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Info</button>
                    </div>
                    <div class="grid grid-cols-1 gap-2">
                        <button type="button" onclick="openIsolirDialog()" class="px-3 py-2 bg-slate-800 dark:bg-slate-700 text-white hover:bg-slate-700 dark:hover:bg-slate-600 border border-slate-700 dark:border-slate-600 rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Dialog Form (Isolir)</button>
                    </div>
                </div>
            </div>
        </x-ui.card>
    </div>
</div>

@section('scripts')
<script>
function openIsolirDialog() {
    // 1. Definisikan string HTML untuk Content/Form
    const formHtml = `
        <form id="formIsolir" onsubmit="submitIsolir(event)">
            <div class="mb-4">
                <label class="block text-sm font-medium text-text-secondary mb-1">
                    Alasan Isolir Koneksi <span class="text-rose-500">*</span>
                </label>
                <textarea id="alasanIsolir" name="alasan" rows="3" required
                    class="w-full rounded-lg border-border bg-surface text-text-main shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm p-2.5 border outline-none"
                    placeholder="Masukkan alasan pemutusan/isolir..."></textarea>
            </div>
            <div class="text-xs text-warning flex items-start gap-1.5 bg-warning-bg p-2.5 rounded border border-warning-border">
                <svg class="h-4 w-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>Tindakan ini akan memutus akses pelanggan dari jaringan secara otomatis.</span>
            </div>
        </form>
    `;

    // 2. Trigger Global Dialog
    window.Dialog.show({
        title: 'Isolir Koneksi Pelanggan',
        icon: 'warning',
        contentHtml: formHtml,
        buttons: [
            {
                text: 'Batal',
                type: 'secondary',
            },
            {
                text: 'Simpan Isolir',
                type: 'submit',
                formId: 'formIsolir'
            }
        ]
    });
}

function submitIsolir(event) {
    event.preventDefault();
    const alasan = document.getElementById('alasanIsolir').value;
    console.log("Memproses isolir dengan alasan:", alasan);
    window.Dialog.close();
    window.Toast.success('Berhasil', 'Koneksi pelanggan telah diisolir.');
}
</script>
@endsection
@endsection
