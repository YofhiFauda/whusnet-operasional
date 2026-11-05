@extends('layouts.app')

@section('title', 'Barang Terpasang di Infrastruktur - Whusnet Operasional')
@section('page_title', 'Terpasang di Infrastruktur')

@php
    $tabs = [
        'sn' => ['label' => 'Barang Ber-SN', 'hint' => 'Lokasi persis — rantai Cabang → OLT/Switch → Slot → PON/Port.'],
        'qty' => ['label' => 'Barang Qty', 'hint' => 'Saldo qty non-kabel per titik infrastruktur (proyeksi dari ledger).'],
        'roll' => ['label' => 'Barang Kabel/Roll', 'hint' => 'Akumulasi total terpasang per titik infrastruktur (dari event pasang di ledger) — bukan saldo persisten seperti SN/Qty, banyak roll fisik ke titik yang sama digabung jadi satu baris; lihat Riwayat Mutasi buat jejak lengkap per roll.'],
    ];
@endphp

@section('content')

<x-warehouse.header
    active="infra-stock"
    title="Barang Terpasang di Infrastruktur"
    subtitle="Barang SN/qty/kabel yang SAAT INI nempel di titik infrastruktur jaringan (bukan log pemakaian periode — itu di halaman Pemakaian Material)."
/>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xs overflow-hidden">

    <div class="flex border-b border-slate-200 dark:border-slate-700">
        @foreach($tabs as $key => $meta)
            <a href="{{ route('warehouse.infra_stock.index', ['tab' => $key]) }}"
               class="px-4 py-2.5 text-xs font-semibold border-b-2 {{ $tab === $key ? 'border-sky-500 text-sky-600 dark:text-sky-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200' }}">
                {{ $meta['label'] }}
            </a>
        @endforeach
    </div>

    <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $tabs[$tab]['hint'] }}</p>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead class="bg-slate-50 dark:bg-slate-900/40 text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold">Barang</th>
                    <th class="px-4 py-2 text-left font-semibold">Kategori</th>
                    @if($tab === 'sn')
                        <th class="px-4 py-2 text-left font-semibold">Serial Number</th>
                    @else
                        <th class="px-4 py-2 text-right font-semibold">{{ $tab === 'roll' ? 'Total Terpasang' : 'Qty' }}</th>
                    @endif
                    <th class="px-4 py-2 text-left font-semibold">Lokasi Infrastruktur</th>
                    <th class="px-4 py-2 text-left font-semibold">{{ $tab === 'roll' ? 'Terakhir Dipasang' : 'Tanggal Pasang' }}</th>
                    <th class="px-4 py-2 text-left font-semibold">Teknisi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($rows as $row)
                    @php($detailRoute = $row['detail_route'] ?? null)
                    <tr>
                        <td class="px-4 py-2 text-slate-700 dark:text-slate-200">
                            {{-- Trace dipicu dari TEKS-nya sendiri (link), BUKAN baris/card —
                                 klik di luar teks barang/SN gak boleh ikut nge-trace (keputusan user). --}}
                            @if($tab === 'roll' && $detailRoute)
                                <a href="{{ $detailRoute }}" class="font-semibold text-sky-600 dark:text-sky-400 hover:underline">{{ $row['item_name'] }}</a>
                            @else
                                {{ $row['item_name'] }}
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-500 dark:text-slate-400">{{ $row['category_name'] }}</td>
                        @if($tab === 'sn')
                            <td class="px-4 py-2 font-mono text-slate-700 dark:text-slate-200">
                                @if($detailRoute)
                                    <a href="{{ $detailRoute }}" class="font-mono font-semibold text-sky-600 dark:text-sky-400 hover:underline">{{ $row['serial_number'] }}</a>
                                @else
                                    {{ $row['serial_number'] }}
                                @endif
                            </td>
                        @else
                            <td class="px-4 py-2 text-right font-mono text-slate-700 dark:text-slate-200">{{ rtrim(rtrim(number_format($row['qty'] ?? $row['total_qty'], 2, ',', '.'), '0'), ',') }} {{ $row['unit'] }}</td>
                        @endif
                        <td class="px-4 py-2 text-slate-600 dark:text-slate-300">
                            {{-- Roll: lokasi pemasangan dikasih bobot/kontras lebih kuat
                                 (bukan abu-abu tipis biasa) biar gak kelewat liat — permintaan
                                 user 2026-11-05, kabel sekarang breadcrumb-nya sampai node persis
                                 (bukan cuma Cabang), jadi harus jelas kebaca. --}}
                            @if($tab === 'roll')
                                <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $row['breadcrumb'] }}</span>
                            @else
                                {{ $row['breadcrumb'] }}
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $row['deployed_at'] ?? '-' }}</td>
                        <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $row['technician_name'] ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-400 dark:text-slate-500">Tidak ada barang yang terpasang di infrastruktur pada tab ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-700">
        {{ $paginator->links() }}
    </div>
</div>

@endsection
