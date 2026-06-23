@extends('layouts.app')

@section('title', 'Dashboard - Whusnet Operasional')
@section('page_title', 'Dashboard Ringkasan')

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
    ];
@endphp

<div class="space-y-6">
    <!-- Filter Panel -->
    <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-none">
        <form method="GET" action="{{ route('dashboard') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="pop_id" class="block text-xs font-semibold text-slate-600 mb-1.5">Filter POP</label>
                <select id="pop_id" name="pop_id" class="w-full h-[38px] px-3 py-2 rounded-md border border-slate-200 text-sm focus:border-sky-500 focus:ring-sky-500 focus:outline-none transition-colors duration-150">
                    <option value="">Semua POP yang dapat diakses</option>
                    @foreach($pops as $pop)
                        <option value="{{ $pop->id }}" @selected((string) $filters['pop_id'] === (string) $pop->id)>
                            {{ $pop->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="period_from" class="block text-xs font-semibold text-slate-600 mb-1.5">Periode Dari</label>
                <input id="period_from" type="month" name="period_from" value="{{ $filters['period_from'] }}" class="w-full h-[38px] px-3 py-2 rounded-md border border-slate-200 text-sm focus:border-sky-500 focus:ring-sky-500 focus:outline-none transition-colors duration-150">
            </div>

            <div>
                <label for="period_to" class="block text-xs font-semibold text-slate-600 mb-1.5">Periode Sampai</label>
                <input id="period_to" type="month" name="period_to" value="{{ $filters['period_to'] }}" class="w-full h-[38px] px-3 py-2 rounded-md border border-slate-200 text-sm focus:border-sky-500 focus:ring-sky-500 focus:outline-none transition-colors duration-150">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn-primary w-full md:w-auto cursor-pointer">
                    Terapkan Filter
                </button>
                <a href="{{ route('dashboard') }}" class="btn-secondary w-full md:w-auto cursor-pointer">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Summary Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Total Pelanggan (Metric Card) -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-none">
            <p class="text-sm font-medium text-slate-500">Total Pelanggan</p>
            <p class="mt-3 text-3xl font-semibold text-slate-900 font-mono">{{ number_format($stats['total_customers']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Sesuai filter POP</p>
        </div>

        <!-- Pelanggan Aktif (Metric Card - Success) -->
        <div class="bg-emerald-50/20 border border-emerald-200 rounded-lg p-5 shadow-none">
            <p class="text-sm font-medium text-emerald-700">Pelanggan Aktif</p>
            <p class="mt-3 text-3xl font-semibold text-emerald-700 font-mono">{{ number_format($stats['active_customers']) }}</p>
            <p class="mt-1 text-xs text-emerald-600"><span class="font-mono">{{ $percent($stats['active_customers'], $stats['total_customers']) }}</span> dari total pelanggan</p>
        </div>

        <!-- Data Belum Lengkap (Operational Status Card - Warning) -->
        <div class="bg-amber-50/20 border border-amber-200 rounded-lg p-5 shadow-none">
            <p class="text-sm font-medium text-amber-700">Data Belum Lengkap</p>
            <p class="mt-3 text-3xl font-semibold text-amber-700 font-mono">{{ number_format($stats['incomplete_customers']) }}</p>
            <p class="mt-1 text-xs text-amber-600"><span class="font-mono">{{ $percent($stats['incomplete_customers'], $stats['total_customers']) }}</span> perlu dilengkapi</p>
        </div>

        <!-- Siap Billing (Metric Card - Info) -->
        <div class="bg-sky-50/20 border border-sky-300 rounded-lg p-5 shadow-none">
            <p class="text-sm font-medium text-sky-700">Siap Billing</p>
            <p class="mt-3 text-3xl font-semibold text-sky-700 font-mono">{{ number_format($stats['ready_billing_customers']) }}</p>
            <p class="mt-1 text-xs text-sky-600"><span class="font-mono">{{ $percent($stats['ready_billing_customers'], $stats['total_customers']) }}</span> siap ditagih</p>
        </div>

        <!-- Tagihan Periode (Metric Card) -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-none">
            <p class="text-sm font-medium text-slate-500">Tagihan Periode ({{ $filters['period_label'] }})</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900 font-mono">{{ $currency($stats['total_invoices_amount']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Berdasarkan periode tagihan</p>
        </div>

        <!-- Pembayaran Periode (Metric Card - Success) -->
        <div class="bg-emerald-50/20 border border-emerald-200 rounded-lg p-5 shadow-none">
            <p class="text-sm font-medium text-emerald-700">Pembayaran Periode ({{ $filters['period_label'] }})</p>
            <p class="mt-3 text-2xl font-semibold text-emerald-700 font-mono">{{ $currency($stats['total_payments_amount']) }}</p>
            <p class="mt-1 text-xs text-emerald-600">Hanya pembayaran valid</p>
        </div>

        <!-- Total Tunggakan (Operational Status Card - Danger) -->
        <div class="bg-rose-50/20 border border-rose-200 rounded-lg p-5 shadow-none">
            <p class="text-sm font-medium text-rose-700">Total Tunggakan</p>
            <p class="mt-3 text-2xl font-semibold text-rose-700 font-mono">{{ $currency($stats['total_unpaid_amount']) }}</p>
            <p class="mt-1 text-xs text-rose-600">Invoice belum lunas pada filter</p>
        </div>

        <!-- Tagihan Jatuh Tempo (Operational Status Card - Danger) -->
        <div class="bg-rose-50/20 border border-rose-200 rounded-lg p-5 shadow-none">
            <p class="text-sm font-medium text-rose-700">Tagihan Jatuh Tempo</p>
            <p class="mt-3 text-3xl font-semibold text-rose-700 font-mono">{{ number_format($stats['due_invoices_count']) }}</p>
            <p class="mt-1 text-xs text-rose-600">Invoice belum lunas melewati batas</p>
        </div>
    </div>

    <!-- Details Section Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Customers by POP (Insight Card) -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-none">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-800">Total Pelanggan per POP</h3>
                <span class="badge badge-neutral font-mono">{{ $customersByPop->count() }} POP</span>
            </div>

            <div class="space-y-3">
                @forelse($customersByPop as $row)
                    <div>
                        <div class="flex justify-between gap-4 text-sm">
                            <span class="font-medium text-slate-700">{{ $row->pop?->name ?? 'Tanpa POP' }}</span>
                            <span class="font-semibold text-slate-900 font-mono">{{ number_format($row->total) }}</span>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-slate-100">
                            <div class="h-2 rounded-full bg-sky-600" style="width: {{ min(100, ((int) $row->total / max(1, (int) $stats['total_customers'])) * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada pelanggan pada filter ini.</p>
                @endforelse
            </div>
        </div>

        <!-- Due Invoices Table -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 xl:col-span-2 shadow-none">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-800">Tagihan Jatuh Tempo</h3>
                @if(auth()->user()->hasPermission('view_invoices'))
                    <a href="{{ route('invoices.index') }}" class="text-xs font-semibold text-sky-700 hover:text-sky-800 transition-colors duration-150">Lihat Semua</a>
                @endif
            </div>

            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="text-left">Invoice</th>
                            <th class="text-left">Pelanggan</th>
                            <th class="text-left">POP</th>
                            <th class="text-left">Jatuh Tempo</th>
                            <th class="text-right">Sisa Tagihan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dueInvoices as $invoice)
                            <tr>
                                <td class="data-cell text-left font-medium text-slate-900">{{ $invoice->invoice_number }}</td>
                                <td class="text-left text-slate-700">{{ $invoice->customer?->full_name ?? '-' }}</td>
                                <td class="text-left text-slate-600">{{ $invoice->pop?->name ?? '-' }}</td>
                                <td class="data-cell text-left text-rose-600 font-semibold">{{ optional($invoice->due_date)->format('d/m/Y') }}</td>
                                <td class="data-cell text-right font-semibold text-slate-900">{{ $currency($invoice->remaining_amount) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-500">Tidak ada tagihan jatuh tempo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Incomplete Customers and Quick Access Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Incomplete Customers Table -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 xl:col-span-2 shadow-none">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-800">Pelanggan yang Perlu Dilengkapi</h3>
                @if(auth()->user()->hasPermission('view_customers'))
                    <a href="{{ route('customers.index', ['completeness_status' => 'perlu_dilengkapi']) }}" class="text-xs font-semibold text-sky-700 hover:text-sky-800 transition-colors duration-150">Lihat Semua</a>
                @endif
            </div>

            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th class="text-left">ID Pelanggan</th>
                            <th class="text-left">Nama</th>
                            <th class="text-left">POP</th>
                            <th class="text-left">Status Kelengkapan</th>
                            <th class="text-left">Terakhir Diupdate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($incompleteCustomers as $customer)
                            <tr>
                                <td class="data-cell text-left font-medium text-slate-900">{{ $customer->customer_code }}</td>
                                <td class="text-left text-slate-700">{{ $customer->full_name }}</td>
                                <td class="text-left text-slate-600">{{ $customer->pop?->name ?? '-' }}</td>
                                <td class="text-left">
                                    <span class="badge badge-warning">
                                        {{ $statusLabels[$customer->data_completeness_status] ?? $customer->data_completeness_status }}
                                    </span>
                                </td>
                                <td class="data-cell text-left text-slate-600">{{ optional($customer->updated_at)->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-500">Tidak ada pelanggan yang perlu dilengkapi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Access Card -->
        <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-none">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Akses Cepat</h3>
            <div class="space-y-3">
                @php $hasQuickAction = false; @endphp

                @if(auth()->user()->hasPermission('view_customers'))
                    @php $hasQuickAction = true; @endphp
                    <a href="{{ route('customers.index') }}" class="flex items-center justify-between rounded-md border border-slate-200 p-3 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-sky-200 hover:text-sky-700 transition-all duration-150 cursor-pointer">
                        <span>Data Pelanggan</span>
                        <svg class="h-4 w-4 text-slate-400 hover:text-sky-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif

                @if(auth()->user()->hasPermission('view_invoices'))
                    @php $hasQuickAction = true; @endphp
                    <a href="{{ route('invoices.index') }}" class="flex items-center justify-between rounded-md border border-slate-200 p-3 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-sky-200 hover:text-sky-700 transition-all duration-150 cursor-pointer">
                        <span>Daftar Tagihan</span>
                        <svg class="h-4 w-4 text-slate-400 hover:text-sky-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif

                @if(auth()->user()->hasPermission('view_payments'))
                    @php $hasQuickAction = true; @endphp
                    <a href="{{ route('payments.index') }}" class="flex items-center justify-between rounded-md border border-slate-200 p-3 text-sm font-medium text-slate-700 hover:bg-slate-50 hover:border-sky-200 hover:text-sky-700 transition-all duration-150 cursor-pointer">
                        <span>Riwayat Pembayaran</span>
                        <svg class="h-4 w-4 text-slate-400 hover:text-sky-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @endif

                @if(!$hasQuickAction)
                    <p class="text-sm text-slate-500">Tidak ada akses cepat yang tersedia untuk peran Anda.</p>
                @endif

                <div class="mt-6 pt-4 border-t border-slate-100">
                    <p class="text-xs font-semibold text-slate-500 mb-3 uppercase tracking-wider">Uji Coba UI (Toast & Dialog)</p>
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <button type="button" onclick="Toast.success('Berhasil', 'Aksi berhasil dilakukan dengan aman.')" class="px-3 py-2 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Success</button>
                        <button type="button" onclick="Toast.error('Sistem Error', 'Terjadi kesalahan saat memproses data Anda. Silakan coba lagi nanti.')" class="px-3 py-2 bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200 rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Error</button>
                        <button type="button" onclick="Toast.warning('Perhatian', 'Kuota penyimpanan Anda hampir penuh.')" class="px-3 py-2 bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Warning</button>
                        <button type="button" onclick="Toast.info('Pembaruan', 'Sistem akan melakukan maintenance pada pukul 00:00 WIB.')" class="px-3 py-2 bg-sky-50 text-sky-700 hover:bg-sky-100 border border-sky-200 rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Info</button>
                    </div>
                    <div class="grid grid-cols-1 gap-2">
                        <button type="button" onclick="openIsolirDialog()" class="px-3 py-2 bg-slate-800 text-white hover:bg-slate-700 border border-slate-700 rounded-md text-xs font-medium transition-colors text-center cursor-pointer">Test Dialog Form (Isolir)</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
function openIsolirDialog() {
    // 1. Definisikan string HTML untuk Content/Form
    const formHtml = `
        <form id="formIsolir" onsubmit="submitIsolir(event)">
            <div class="mb-4">
                <label class="block text-sm font-medium text-slate-700 mb-1">
                    Alasan Isolir Koneksi <span class="text-rose-500">*</span>
                </label>
                <textarea id="alasanIsolir" name="alasan" rows="3" required
                    class="w-full rounded-lg border-slate-300 shadow-sm focus:border-sky-500 focus:ring-sky-500 text-sm p-2.5 border outline-none"
                    placeholder="Masukkan alasan pemutusan/isolir..."></textarea>
            </div>
            <div class="text-xs text-amber-600 flex items-start gap-1.5 bg-amber-50 p-2.5 rounded border border-amber-100">
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
