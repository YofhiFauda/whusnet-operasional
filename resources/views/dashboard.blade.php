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
    <div class="bg-white border border-slate-200 rounded-lg p-4">
        <form method="GET" action="{{ route('dashboard') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label for="pop_id" class="block text-xs font-semibold text-slate-600 mb-1">Filter POP</label>
                <select id="pop_id" name="pop_id" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
                    <option value="">Semua POP yang dapat diakses</option>
                    @foreach($pops as $pop)
                        <option value="{{ $pop->id }}" @selected((string) $filters['pop_id'] === (string) $pop->id)>
                            {{ $pop->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="period_from" class="block text-xs font-semibold text-slate-600 mb-1">Periode Dari</label>
                <input id="period_from" type="month" name="period_from" value="{{ $filters['period_from'] }}" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
            </div>

            <div>
                <label for="period_to" class="block text-xs font-semibold text-slate-600 mb-1">Periode Sampai</label>
                <input id="period_to" type="month" name="period_to" value="{{ $filters['period_to'] }}" class="w-full rounded-md border-slate-300 text-sm focus:border-sky-500 focus:ring-sky-500">
            </div>

            <div class="flex gap-2">
                <button type="submit" class="inline-flex justify-center rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">
                    Terapkan Filter
                </button>
                <a href="{{ route('dashboard') }}" class="inline-flex justify-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white border border-slate-200 rounded-lg p-5">
            <p class="text-sm font-medium text-slate-500">Total Pelanggan</p>
            <p class="mt-3 text-3xl font-semibold text-slate-900">{{ number_format($stats['total_customers']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Sesuai filter POP</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-5">
            <p class="text-sm font-medium text-slate-500">Pelanggan Aktif</p>
            <p class="mt-3 text-3xl font-semibold text-green-700">{{ number_format($stats['active_customers']) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $percent($stats['active_customers'], $stats['total_customers']) }} dari total pelanggan</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-5">
            <p class="text-sm font-medium text-slate-500">Data Belum Lengkap</p>
            <p class="mt-3 text-3xl font-semibold text-amber-700">{{ number_format($stats['incomplete_customers']) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $percent($stats['incomplete_customers'], $stats['total_customers']) }} perlu dilengkapi</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-5">
            <p class="text-sm font-medium text-slate-500">Siap Billing</p>
            <p class="mt-3 text-3xl font-semibold text-sky-700">{{ number_format($stats['ready_billing_customers']) }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $percent($stats['ready_billing_customers'], $stats['total_customers']) }} siap ditagih</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-5">
            <p class="text-sm font-medium text-slate-500">Tagihan Periode {{ $filters['period_label'] }}</p>
            <p class="mt-3 text-2xl font-semibold text-slate-900">{{ $currency($stats['total_invoices_amount']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Berdasarkan periode tagihan</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-5">
            <p class="text-sm font-medium text-slate-500">Pembayaran Periode {{ $filters['period_label'] }}</p>
            <p class="mt-3 text-2xl font-semibold text-emerald-700">{{ $currency($stats['total_payments_amount']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Hanya pembayaran valid</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-5">
            <p class="text-sm font-medium text-slate-500">Total Tunggakan</p>
            <p class="mt-3 text-2xl font-semibold text-rose-700">{{ $currency($stats['total_unpaid_amount']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Invoice belum lunas pada periode filter</p>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-5">
            <p class="text-sm font-medium text-slate-500">Tagihan Jatuh Tempo</p>
            <p class="mt-3 text-3xl font-semibold text-rose-700">{{ number_format($stats['due_invoices_count']) }}</p>
            <p class="mt-1 text-xs text-slate-500">Invoice belum lunas melewati jatuh tempo</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="bg-white border border-slate-200 rounded-lg p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-800">Total Pelanggan per POP</h3>
                <span class="text-xs text-slate-500">{{ $customersByPop->count() }} POP</span>
            </div>

            <div class="space-y-3">
                @forelse($customersByPop as $row)
                    <div>
                        <div class="flex justify-between gap-4 text-sm">
                            <span class="font-medium text-slate-700">{{ $row->pop?->name ?? 'Tanpa POP' }}</span>
                            <span class="font-semibold text-slate-900">{{ number_format($row->total) }}</span>
                        </div>
                        <div class="mt-1 h-2 rounded-full bg-slate-100">
                            <div class="h-2 rounded-full bg-sky-600" style="width: {{ min(100, ((int) $row->total / max(1, (int) $stats['total_customers'])) * 100) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum ada pelanggan pada filter ini.</p>
                @endforelse
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-5 xl:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-800">Tagihan Jatuh Tempo</h3>
                @if(auth()->user()->hasPermission('view_invoices'))
                    <a href="{{ route('invoices.index') }}" class="text-xs font-semibold text-sky-700 hover:text-sky-800">Lihat Tagihan</a>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="py-2 pr-4">Invoice</th>
                            <th class="py-2 pr-4">Pelanggan</th>
                            <th class="py-2 pr-4">POP</th>
                            <th class="py-2 pr-4">Jatuh Tempo</th>
                            <th class="py-2 text-right">Sisa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($dueInvoices as $invoice)
                            <tr>
                                <td class="py-3 pr-4 font-medium text-slate-800">{{ $invoice->invoice_number }}</td>
                                <td class="py-3 pr-4 text-slate-700">{{ $invoice->customer?->full_name ?? '-' }}</td>
                                <td class="py-3 pr-4 text-slate-600">{{ $invoice->pop?->name ?? '-' }}</td>
                                <td class="py-3 pr-4 text-rose-700">{{ optional($invoice->due_date)->format('d/m/Y') }}</td>
                                <td class="py-3 text-right font-semibold text-slate-900">{{ $currency($invoice->remaining_amount) }}</td>
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

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="bg-white border border-slate-200 rounded-lg p-5 xl:col-span-2">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-800">Pelanggan yang Perlu Dilengkapi</h3>
                @if(auth()->user()->hasPermission('view_customers'))
                    <a href="{{ route('customers.index', ['completeness_status' => 'perlu_dilengkapi']) }}" class="text-xs font-semibold text-sky-700 hover:text-sky-800">Lihat Pelanggan</a>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="py-2 pr-4">ID</th>
                            <th class="py-2 pr-4">Nama</th>
                            <th class="py-2 pr-4">POP</th>
                            <th class="py-2 pr-4">Status Kelengkapan</th>
                            <th class="py-2">Update</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($incompleteCustomers as $customer)
                            <tr>
                                <td class="py-3 pr-4 font-medium text-slate-800">{{ $customer->customer_code }}</td>
                                <td class="py-3 pr-4 text-slate-700">{{ $customer->full_name }}</td>
                                <td class="py-3 pr-4 text-slate-600">{{ $customer->pop?->name ?? '-' }}</td>
                                <td class="py-3 pr-4">
                                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">
                                        {{ $statusLabels[$customer->data_completeness_status] ?? $customer->data_completeness_status }}
                                    </span>
                                </td>
                                <td class="py-3 text-slate-600">{{ optional($customer->updated_at)->format('d/m/Y') }}</td>
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

        <div class="bg-white border border-slate-200 rounded-lg p-5">
            <h3 class="text-sm font-semibold text-slate-800 mb-4">Akses Cepat</h3>
            <div class="space-y-3">
                @php $hasQuickAction = false; @endphp

                @if(auth()->user()->hasPermission('view_customers'))
                    @php $hasQuickAction = true; @endphp
                    <a href="{{ route('customers.index') }}" class="flex items-center justify-between rounded-md border border-slate-200 p-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <span>Data Pelanggan</span>
                        <span class="text-sky-700">Buka</span>
                    </a>
                @endif

                @if(auth()->user()->hasPermission('view_invoices'))
                    @php $hasQuickAction = true; @endphp
                    <a href="{{ route('invoices.index') }}" class="flex items-center justify-between rounded-md border border-slate-200 p-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <span>Daftar Tagihan</span>
                        <span class="text-sky-700">Buka</span>
                    </a>
                @endif

                @if(auth()->user()->hasPermission('view_payments'))
                    @php $hasQuickAction = true; @endphp
                    <a href="{{ route('payments.index') }}" class="flex items-center justify-between rounded-md border border-slate-200 p-3 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <span>Riwayat Pembayaran</span>
                        <span class="text-sky-700">Buka</span>
                    </a>
                @endif

                @if(!$hasQuickAction)
                    <p class="text-sm text-slate-500">Tidak ada akses cepat yang tersedia untuk peran Anda.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
