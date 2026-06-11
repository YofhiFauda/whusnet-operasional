@extends('layouts.app')

@section('title', 'Dashboard - Whusnet Operasional')
@section('page_title', 'Dashboard')

@section('content')
<!-- KPI Cards Row -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Card 1: Total Customers -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Total Pelanggan</span>
            <div class="p-2 bg-sky-50 rounded-md text-sky-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <h3 class="text-3xl font-semibold text-slate-800 data-text">{{ $stats['total_customers'] }}</h3>
            <p class="text-xs text-slate-500 mt-1">Pelanggan terdaftar</p>
        </div>
    </div>

    <!-- Card 2: Active Services -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Pelanggan Aktif</span>
            <div class="p-2 bg-green-50 rounded-md text-green-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <h3 class="text-3xl font-semibold text-slate-800 data-text">{{ $stats['active_customers'] }}</h3>
            <p class="text-xs text-green-600 mt-1 font-medium flex items-center gap-1">
                <span>{{ number_format(($stats['active_customers'] / max(1, $stats['total_customers'])) * 100, 1) }}%</span>
                <span class="text-slate-500 font-normal">dari total pelanggan</span>
            </p>
        </div>
    </div>

    <!-- Card 3: Incomplete Customers -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Data Belum Lengkap</span>
            <div class="p-2 bg-rose-50 rounded-md text-rose-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <h3 class="text-3xl font-semibold text-slate-800 data-text">{{ $stats['incomplete_customers'] }}</h3>
            <p class="text-xs text-rose-600 mt-1 font-medium flex items-center gap-1">
                <span>{{ number_format(($stats['incomplete_customers'] / max(1, $stats['total_customers'])) * 100, 1) }}%</span>
                <span class="text-slate-500 font-normal">perlu dilengkapi</span>
            </p>
        </div>
    </div>

    <!-- Card 4: Internet Packages -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Paket Layanan</span>
            <div class="p-2 bg-purple-50 rounded-md text-purple-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <h3 class="text-3xl font-semibold text-slate-800 data-text">{{ $stats['total_packages'] }}</h3>
            <p class="text-xs text-slate-500 mt-1">Pilihan paket aktif</p>
        </div>
    </div>

    <!-- Card 5: Districts Covered -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500">Cakupan Wilayah</span>
            <div class="p-2 bg-amber-50 rounded-md text-amber-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <h3 class="text-3xl font-semibold text-slate-800 data-text">{{ $stats['total_districts'] }}</h3>
            <p class="text-xs text-slate-500 mt-1">Kecamatan tercover</p>
        </div>
    </div>

    <!-- Card 6: Total Tagihan Bulan Ini (Placeholder) -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 hover:shadow-md transition-shadow opacity-75 relative group">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500 flex items-center gap-1.5">
                Tagihan Bulan Ini
                <span class="px-1.5 py-0.5 text-[10px] bg-slate-100 text-slate-500 rounded font-medium">Sprint 5</span>
            </span>
            <div class="p-2 bg-blue-50 rounded-md text-blue-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <h3 class="text-3xl font-semibold text-slate-400">Rp {{ number_format($stats['total_invoices_amount']) }}</h3>
            <p class="text-xs text-slate-400 mt-1">Placeholder (Belum Aktif)</p>
        </div>
    </div>

    <!-- Card 7: Total Pembayaran Bulan Ini (Placeholder) -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 hover:shadow-md transition-shadow opacity-75 relative group">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500 flex items-center gap-1.5">
                Pembayaran Bulan Ini
                <span class="px-1.5 py-0.5 text-[10px] bg-slate-100 text-slate-500 rounded font-medium">Sprint 6</span>
            </span>
            <div class="p-2 bg-emerald-50 rounded-md text-emerald-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <h3 class="text-3xl font-semibold text-slate-400">Rp {{ number_format($stats['total_payments_amount']) }}</h3>
            <p class="text-xs text-slate-400 mt-1">Placeholder (Belum Aktif)</p>
        </div>
    </div>

    <!-- Card 8: Total Tunggakan (Placeholder) -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 hover:shadow-md transition-shadow opacity-75 relative group">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500 flex items-center gap-1.5">
                Total Tunggakan
                <span class="px-1.5 py-0.5 text-[10px] bg-slate-100 text-slate-500 rounded font-medium">Sprint 5</span>
            </span>
            <div class="p-2 bg-rose-50 rounded-md text-rose-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
        </div>
        <div class="mt-4">
            <h3 class="text-3xl font-semibold text-slate-400">Rp {{ number_format($stats['total_unpaid_amount']) }}</h3>
            <p class="text-xs text-slate-400 mt-1">Placeholder (Belum Aktif)</p>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
    <!-- Chart 1: Registration Trend -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 lg:col-span-2">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Tren Pendaftaran Pelanggan Baru</h3>
        <div id="registration-trend-chart" class="h-80"></div>
    </div>

    <!-- Chart 2: Status Distribution -->
    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Status Pelanggan</h3>
        <div id="status-distribution-chart" class="h-80 flex items-center justify-center"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Chart 3: Category Distribution -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 lg:col-span-2">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Langganan per Kategori Paket</h3>
        <div id="category-chart" class="h-80"></div>
    </div>

    <!-- Quick Actions Card -->
    <div class="bg-white border border-slate-200 rounded-lg p-6">
        <h3 class="text-sm font-semibold text-slate-700 mb-4">Akses Cepat</h3>
        <div class="space-y-3">
            @php $hasQuickAction = false; @endphp

            @if(auth()->user()->hasPermission('view_customers'))
                @php $hasQuickAction = true; @endphp
                <a href="/customers" class="flex items-center justify-between p-3 border border-slate-100 rounded-md hover:bg-slate-50 transition-colors group cursor-pointer">
                    <span class="text-xs font-medium text-slate-700">Lihat Semua Pelanggan</span>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-sky-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endif

            @if(auth()->user()->hasPermission('view_pop'))
                @php $hasQuickAction = true; @endphp
                <a href="/master/wilayah" class="flex items-center justify-between p-3 border border-slate-100 rounded-md hover:bg-slate-50 transition-colors group cursor-pointer">
                    <span class="text-xs font-medium text-slate-700">Kelola Master Wilayah</span>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-sky-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endif

            @if(auth()->user()->hasPermission('view_packages'))
                @php $hasQuickAction = true; @endphp
                <a href="/master/paket" class="flex items-center justify-between p-3 border border-slate-100 rounded-md hover:bg-slate-50 transition-colors group cursor-pointer">
                    <span class="text-xs font-medium text-slate-700">Lihat Master Paket Internet</span>
                    <svg class="h-4 w-4 text-slate-400 group-hover:text-sky-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endif

            @if(!$hasQuickAction)
                <p class="text-xs text-slate-500 italic">Tidak ada akses cepat yang tersedia untuk peran Anda.</p>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Colors from Design System
        const skyBlue = '#0284C7';
        const slate900 = '#0F172A';
        const green600 = '#16A34A';
        const amber600 = '#D97706';
        const red600 = '#DC2626';

        // Trend Chart Data
        const trends = @json($trends);
        const trendMonths = Object.keys(trends);
        const trendCounts = Object.values(trends);

        var trendOptions = {
            chart: {
                type: 'area',
                height: 320,
                fontFamily: 'Inter, sans-serif',
                toolbar: { show: false }
            },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', colors: [skyBlue], width: 2 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 100]
                }
            },
            series: [{
                name: 'Pendaftaran Baru',
                data: trendCounts
            }],
            xaxis: {
                categories: trendMonths,
                labels: {
                    style: {
                        colors: '#64748B',
                        fontSize: '11px',
                        fontFamily: 'JetBrains Mono, monospace'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: '#64748B',
                        fontSize: '11px',
                        fontFamily: 'JetBrains Mono, monospace'
                    }
                }
            },
            grid: { borderColor: '#F1F5F9' },
            colors: [skyBlue]
        };

        var trendChart = new ApexCharts(document.querySelector("#registration-trend-chart"), trendOptions);
        trendChart.render();

        // Status Chart Data
        const statusData = @json($statusData);
        const statusLabels = Object.keys(statusData);
        const statusCounts = Object.values(statusData);

        var statusOptions = {
            chart: {
                type: 'donut',
                height: 320,
                fontFamily: 'Inter, sans-serif'
            },
            labels: statusLabels,
            series: statusCounts,
            colors: [skyBlue, red600, '#94A3B8', green600, amber600],
            legend: {
                position: 'bottom',
                fontSize: '12px',
                fontFamily: 'Inter, sans-serif'
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                formatter: function (w) {
                                    return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                },
                                style: {
                                    fontSize: '16px',
                                    fontWeight: 600,
                                    color: '#0F172A',
                                    fontFamily: 'Inter, sans-serif'
                                }
                            }
                        }
                    }
                }
            },
            dataLabels: { enabled: false }
        };

        var statusChart = new ApexCharts(document.querySelector("#status-distribution-chart"), statusOptions);
        statusChart.render();

        // Category Chart Data
        const categoryData = @json($categoryData);
        const categoryLabels = Object.keys(categoryData);
        const categoryCounts = Object.values(categoryData);

        var categoryOptions = {
            chart: {
                type: 'bar',
                height: 320,
                fontFamily: 'Inter, sans-serif',
                toolbar: { show: false }
            },
            plotOptions: {
                bar: {
                    horizontal: false,
                    columnWidth: '40%',
                    endingShape: 'rounded',
                    borderRadius: 4
                },
            },
            dataLabels: { enabled: false },
            stroke: { show: true, width: 2, colors: ['transparent'] },
            series: [{
                name: 'Jumlah Pelanggan',
                data: categoryCounts
            }],
            xaxis: {
                categories: categoryLabels,
                labels: {
                    style: {
                        colors: '#64748B',
                        fontSize: '11px',
                        fontFamily: 'Inter, sans-serif'
                    }
                }
            },
            yaxis: {
                labels: {
                    style: {
                        colors: '#64748B',
                        fontSize: '11px',
                        fontFamily: 'JetBrains Mono, monospace'
                    }
                }
            },
            fill: { opacity: 1 },
            colors: [skyBlue],
            grid: { borderColor: '#F1F5F9' }
        };

        var categoryChart = new ApexCharts(document.querySelector("#category-chart"), categoryOptions);
        categoryChart.render();
    });
</script>
@endsection
