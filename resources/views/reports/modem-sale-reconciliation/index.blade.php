@extends('layouts.app')

@section('title', 'Rekonsiliasi Modem Terjual')
@section('page_title', 'Rekonsiliasi Modem Terjual')

{{-- Type A — Data List (design-system/whusnet-operasional/Design.md §1.3):
     header & filter bar naked, summary strip datar, SATU card = panel tabel.
     Warna status hanya di badge/teks, tidak pernah di background section. --}}
@section('content')
@php
    $currency = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $hasActiveFilter = collect($filters)->filter(fn ($value) => $value !== '')->isNotEmpty();
    $inputClass = 'h-9 px-3 rounded-lg border border-border bg-surface text-[13px] text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-sky-600/12';
@endphp

{{-- ── Page Header (naked) ─────────────────────────────────────────── --}}
<div class="page-header flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-5">
    <div class="flex flex-col gap-1">
        <h1 class="text-[22px] font-bold leading-tight text-text-main">SN Terjual Belum Ditagih</h1>
        <p class="text-[13px] text-text-muted max-w-3xl">
            Setiap SN "Beli dari Gudang" wajib berujung ke satu baris tagihan. Daftar ini berisi SN yang belum tertagih —
            sebagian wajar karena masih menunggu langkah berikutnya, yang berstatus <strong class="text-text-secondary">Anomali</strong> perlu ditindaklanjuti.
        </p>
    </div>
    @if($canExport)
        <a href="{{ route('reports.modem-sale-reconciliation.export', array_filter($filters, fn ($value) => $value !== '')) }}"
           hx-boost="false"
           class="btn-secondary inline-flex shrink-0 items-center gap-2 h-9 px-4 rounded-lg text-[13px] font-semibold">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Export Excel
        </a>
    @endif
</div>

{{-- ── Summary Strip (flat bar, bukan grid card) ───────────────────── --}}
<div class="summary-strip flex-wrap">
    <div class="summary-col min-w-[50%] sm:min-w-0">
        <span class="summary-label">Belum Tertagih</span>
        <span class="summary-value">{{ number_format($summary['total'], 0, ',', '.') }}</span>
        <span class="summary-sub">SN</span>
    </div>
    <div class="summary-col min-w-[50%] sm:min-w-0">
        <span class="summary-label">Anomali</span>
        <span class="summary-value {{ $summary['anomaly'] > 0 ? 'error' : '' }}">{{ number_format($summary['anomaly'], 0, ',', '.') }}</span>
        <span class="summary-sub">perlu ditindaklanjuti</span>
    </div>
    <div class="summary-col min-w-[50%] sm:min-w-0">
        <span class="summary-label">Menunggu Proses</span>
        <span class="summary-value {{ $summary['waiting'] > 0 ? 'warning' : '' }}">{{ number_format($summary['waiting'], 0, ',', '.') }}</span>
        <span class="summary-sub">BD / verifikasi CS</span>
    </div>
    <div class="summary-col min-w-[50%] sm:min-w-0">
        <span class="summary-label">Nilai Belum Ditagih</span>
        <span class="summary-value">{{ $currency($summary['unbilled_amount']) }}</span>
        <span class="summary-sub">total harga jual</span>
    </div>
</div>

{{-- ── Filter Bar (naked) ──────────────────────────────────────────── --}}
<form method="GET" action="{{ route('reports.modem-sale-reconciliation.index') }}" class="filter-bar flex flex-wrap items-end gap-2.5 mb-4">
    <div class="flex flex-col gap-1 w-full sm:w-auto sm:flex-1 sm:max-w-[360px]">
        <label for="search" class="text-[11px] font-semibold uppercase tracking-wide text-text-muted">Cari</label>
        <div class="flex items-center gap-2 h-9 px-3 rounded-full border border-border bg-surface focus-within:border-primary focus-within:ring-2 focus-within:ring-sky-600/12">
            <svg class="h-4 w-4 shrink-0 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.3-4.3"/></svg>
            <input type="search" name="search" id="search" value="{{ $filters['search'] }}" placeholder="SN, nama, atau CID pelanggan"
                   class="flex-1 min-w-0 border-0 bg-transparent p-0 text-[13px] text-text-main placeholder:text-text-disabled focus:outline-none focus:ring-0">
        </div>
    </div>

    <div class="flex flex-col gap-1 w-[calc(50%-5px)] sm:w-44">
        <label for="pop_id" class="text-[11px] font-semibold uppercase tracking-wide text-text-muted">Cabang</label>
        <select name="pop_id" id="pop_id" class="{{ $inputClass }} pr-8">
            <option value="">Semua Cabang</option>
            @foreach($pops as $pop)
                <option value="{{ $pop->id }}" @selected((string) $filters['pop_id'] === (string) $pop->id)>{{ $pop->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex flex-col gap-1 w-[calc(50%-5px)] sm:w-48">
        <label for="status" class="text-[11px] font-semibold uppercase tracking-wide text-text-muted">Status</label>
        <select name="status" id="status" class="{{ $inputClass }} pr-8">
            <option value="">Semua Status</option>
            @foreach($statusOptions as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex flex-col gap-1 w-[calc(50%-5px)] sm:w-40">
        <label for="date_from" class="text-[11px] font-semibold uppercase tracking-wide text-text-muted">Jual Dari</label>
        <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] }}" class="{{ $inputClass }}">
    </div>

    <div class="flex flex-col gap-1 w-[calc(50%-5px)] sm:w-40">
        <label for="date_to" class="text-[11px] font-semibold uppercase tracking-wide text-text-muted">Jual Sampai</label>
        <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] }}" class="{{ $inputClass }}">
    </div>

    <input type="hidden" name="per_page" value="{{ $rows->perPage() }}">

    <div class="flex gap-2">
        <button type="submit" class="btn-primary h-9 px-4 rounded-lg text-[13px] font-semibold cursor-pointer">Terapkan</button>
        @if($hasActiveFilter)
            <a href="{{ route('reports.modem-sale-reconciliation.index') }}" class="btn-secondary inline-flex items-center h-9 px-4 rounded-lg text-[13px] font-semibold">Reset</a>
        @endif
    </div>
</form>

{{-- ── Table Panel (satu-satunya card) ─────────────────────────────── --}}
<div class="rounded-lg border border-border bg-surface overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-border bg-surface-muted/50 text-[11px] font-semibold uppercase tracking-wide text-text-muted">
                    <th class="px-4 py-3">SN</th>
                    <th class="px-4 py-3">Barang</th>
                    <th class="px-4 py-3">Pelanggan</th>
                    <th class="px-4 py-3">Cabang</th>
                    <th class="px-4 py-3 text-right">Harga Jual</th>
                    <th class="px-4 py-3">Tanggal Jual</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border text-[13px]">
                @forelse($rows as $row)
                    @php $sale = $row['sale']; @endphp
                    <tr class="hover:bg-surface-muted/40 transition-colors">
                        <td class="px-4 py-3 font-mono text-text-main whitespace-nowrap">{{ $sale->inventorySerial?->serial_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-text-secondary">{{ $sale->inventorySerial?->item?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-text-main">{{ $sale->customer?->full_name ?? '—' }}</div>
                            <div class="font-mono text-[11px] text-text-muted">{{ $sale->customer?->cid ?? $sale->customer?->customer_code ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3 text-text-secondary whitespace-nowrap">{{ $sale->customer?->pop?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-mono tabular-nums text-text-main whitespace-nowrap">{{ $currency($sale->price) }}</td>
                        <td class="px-4 py-3 font-mono text-[12px] text-text-secondary whitespace-nowrap">{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 min-w-[200px]">
                            <x-ui.badge :variant="$row['status'] === \App\Services\ModemSaleReconciliationService::STATUS_ANOMALY ? 'error' : 'warning'">
                                {{ $row['status_label'] }}
                            </x-ui.badge>
                            @if($row['reason'])
                                <p class="mt-1 max-w-[260px] text-[11px] leading-snug text-text-muted">{{ $row['reason'] }}</p>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-[13px] text-text-muted">
                            {{ $hasActiveFilter ? 'Tidak ada SN yang cocok dengan filter.' : 'Semua SN terjual sudah tertagih.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-list-pagination :paginator="$rows" label="SN" class="px-4 py-3 border-t border-border" />
</div>
@endsection
