@extends('layouts.app')

@section('title', 'Bon Penerimaan '.$reference.' - Whusnet Operasional')
@section('page_title', 'Barang Masuk '.$reference)

@section('content')

@php $first = $transactions->first(); @endphp

<x-warehouse.header active="stock" title="Faktur Penerimaan Barang #{{ $reference }}" subtitle="Bukti mutasi stok masuk ke Gudang Pusat dengan snapshot harga satuan material." backUrl="{{ route('warehouse.stock.index') }}" />

<div class="space-y-6">
    <!-- Header Invoice Card -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-6 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-11 h-11 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center border border-emerald-100 dark:border-emerald-800/60 shadow-xs shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Gudang Penerima: {{ $first->toPop->name ?? '-' }}</h3>
                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
                            TERSIMPAN
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Dicatat pada <strong class="text-slate-700 dark:text-slate-200">{{ $first->created_at->translatedFormat('d F Y • H:i') }} WIB</strong> oleh <strong class="text-slate-700 dark:text-slate-200">{{ $first->createdBy?->name ?? 'Sistem' }}</strong>
                    </p>
                    @if($first->notes)
                    <div class="mt-2 text-xs font-medium text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/40 px-3 py-1.5 rounded-lg border border-slate-100 dark:border-slate-700/50">
                        <span class="text-slate-400">Catatan/Surat Jalan:</span> {{ $first->notes }}
                    </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0 print:hidden">
                @if($transactions->contains(fn ($line) => $line->roll_id))
                <a href="{{ route('warehouse.receive.rolls.print', $reference) }}" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 rounded-lg text-xs font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak Label Roll (Semua)</span>
                </a>
                @endif
                @if($transactions->contains(fn ($line) => $line->serial_id && $line->item->auto_generate_serial))
                <a href="{{ route('warehouse.receive.serials.print', $reference) }}" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 rounded-lg text-xs font-semibold transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak Label SN (Semua)</span>
                </a>
                @endif
                <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-lg text-xs font-semibold transition-colors cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.04-.36-2.126-.36-3.238A8.04 8.04 0 0112 2.55a8.04 8.04 0 015.64 8.041c0 1.112-.12 2.198-.36 3.238m-10.56 0A9.956 9.956 0 0012 18.001c2.148 0 4.13-.674 5.64-1.815m-10.56 0L4.5 19.5m15-1.5l2.25 1.5"/></svg>
                    <span>Cetak Bon</span>
                </button>
                <a href="{{ route('warehouse.stock.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                    Kembali ke Stok
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Invoice Table (Rincian per Barang & Nilai Faktur) -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs print:border-none print:shadow-none" id="print-area">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Ringkasan Faktur Penerimaan Barang</h4>
            <span class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400">Total: Rp {{ number_format($grandTotal, 0, ',', '.') }}</span>
        </div>
        <div class="overflow-x-auto scroll-smooth">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang / Item</th>
                        <th class="px-6 py-3.5 text-center text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah Qty</th>
                        <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Harga Beli Satuan</th>
                        <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Subtotal (Rp)</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                    @foreach($summaryLines as $line)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-3.5">
                            <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $line->item->name }}</div>
                            <div class="text-[11px] font-mono text-slate-400">{{ $line->item->code }} • {{ $line->item->category?->name ?? '-' }}</div>
                        </td>
                        <td class="px-6 py-3.5 text-center font-mono text-sm font-bold text-slate-800 dark:text-slate-200">
                            @if($line->unit === 'roll')
                                <div>{{ number_format((float) $line->qty, 0, ',', '.') }} roll</div>
                                <div class="text-[11px] font-normal text-slate-400 font-sans mt-0.5">
                                    {{ number_format((float) $line->meter_total, 0, ',', '.') }} meter ({{ number_format((float) $line->meter_per_roll, 0, ',', '.') }} m/roll)
                                </div>
                            @else
                                <div>{{ rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',') }} {{ $line->unit }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono text-sm font-semibold text-slate-700 dark:text-slate-300">
                            @if($line->unit === 'roll')
                                <div>Rp {{ number_format((float) $line->unit_price_snapshot, 0, ',', '.') }} <span class="text-xs font-normal text-slate-400">/ roll</span></div>
                                <div class="text-[11px] font-normal text-slate-400 font-sans mt-0.5">
                                    Rp {{ number_format((float) $line->price_per_meter, 0, ',', '.') }} / meter
                                </div>
                            @else
                                <div>Rp {{ number_format((float) $line->unit_price_snapshot, 0, ',', '.') }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">
                            Rp {{ number_format((float) $line->subtotal, 0, ',', '.') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 dark:bg-slate-800/80 border-t-2 border-slate-200 dark:border-slate-700">
                    <tr>
                        <td colspan="3" class="px-6 py-3.5 text-right text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                            Total Nilai Faktur Penerimaan
                        </td>
                        <td class="px-6 py-3.5 text-right font-mono text-base font-extrabold text-emerald-600 dark:text-emerald-400">
                            Rp {{ number_format($grandTotal, 0, ',', '.') }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Detailed Serial Number & Roll ID Breakdown -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Rincian Fisik Serial Number &amp; Roll ID Diterima</h4>
            <span class="text-xs text-slate-400">{{ $transactions->count() }} unit terdaftar</span>
        </div>
        <div class="overflow-x-auto scroll-smooth">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang / Item</th>
                        <th class="px-6 py-3 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Detail / Serial Number / Roll ID</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Panjang / Qty Fisik</th>
                        <th class="px-6 py-3 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Aksi / Cetak</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                    @foreach($transactions as $line)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-3">
                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200">{{ $line->item->name }}</div>
                            <div class="text-[10px] font-mono text-slate-400">{{ $line->item->code }}</div>
                        </td>
                        <td class="px-6 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">
                            @if($line->serial)
                                @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                <a href="{{ route('warehouse.traceability.index', ['sn' => $line->serial->serial_number]) }}" class="text-sky-600 dark:text-sky-400 font-bold hover:underline print:text-slate-800">
                                    SN: {{ $line->serial->serial_number }}
                                </a>
                                @else
                                <span class="font-bold">SN: {{ $line->serial->serial_number }}</span>
                                @endif
                            @elseif($line->roll)
                                <a href="{{ route('warehouse.traceability.index', ['roll' => $line->roll->roll_code]) }}" class="text-amber-600 dark:text-amber-400 font-bold hover:underline print:text-slate-800">
                                    Roll: {{ $line->roll->roll_code }}
                                </a>
                            @else
                                <span class="text-slate-400">{{ $line->lot_no ? "Lot: {$line->lot_no}" : 'Reguler non-serial' }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right font-mono text-xs font-semibold text-slate-700 dark:text-slate-300">
                            @if($line->serial)
                                1 unit
                            @elseif($line->roll)
                                {{ rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',') }} meter
                            @else
                                {{ rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',').' '.$line->item->unit }}
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right text-xs">
                            @if($line->serial && $line->item->auto_generate_serial)
                                <a href="{{ route('warehouse.serials.print', $line->serial) }}" target="_blank" class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 hover:underline print:hidden font-semibold">
                                    <span>Cetak Label SN</span>
                                </a>
                            @elseif($line->roll)
                                <a href="{{ route('warehouse.rolls.print', $line->roll) }}" target="_blank" class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 hover:underline print:hidden font-semibold">
                                    <span>Cetak Label Roll</span>
                                </a>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
