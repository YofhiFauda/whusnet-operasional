@extends('layouts.app')

@section('title', 'Transfer '.$transfer->reference_number.' - Whusnet Operasional')
@section('page_title', 'Transfer '.$transfer->reference_number)

@section('content')

<x-warehouse.header active="transfers-pending" title="Surat Jalan Transfer #{{ $transfer->reference_number }}" subtitle="Dokumen perpindahan material & perangkat antar gudang POP Whusnet." backUrl="{{ route('warehouse.transfers.pending') }}" />

<div class="space-y-6">
    <!-- Header Status Card -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-6 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-11 h-11 rounded-lg bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-800/60 shadow-xs shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">
                            {{ $transfer->fromPop->name }} <span class="text-slate-400">→</span> {{ $transfer->toPop->name }}
                        </h3>
                        @php
                            $statusBadge = match($transfer->status->value) {
                                'in_transit' => 'bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-800',
                                'received' => 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800',
                                'received_partial' => 'bg-rose-50 dark:bg-rose-950/40 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800',
                                default => 'bg-slate-100 text-slate-600 border-slate-200',
                            };
                        @endphp
                        <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $statusBadge }}">
                            {{ $transfer->status->label() }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Dibuat pada <strong class="text-slate-700 dark:text-slate-200">{{ $transfer->created_at->translatedFormat('d F Y • H:i') }} WIB</strong> oleh <strong class="text-slate-700 dark:text-slate-200">{{ $transfer->createdBy?->name ?? 'Sistem' }}</strong>
                    </p>
                    @if($transfer->receivedBy)
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-medium">
                        ✓ Dikonfirmasi terima pada {{ $transfer->received_at->translatedFormat('d F Y • H:i') }} WIB oleh {{ $transfer->receivedBy->name }}
                    </p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('warehouse.transfers.surat-jalan', $transfer) }}" target="_blank" class="px-3 py-2 text-xs font-semibold border border-slate-200 dark:border-slate-700 rounded-md text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                    Cetak Surat Jalan
                </a>
                @if(auth()->user()->hasPermission('warehouse_transfer_invoice.view'))
                <a href="{{ route('warehouse.transfers.invoice', $transfer) }}" target="_blank" class="px-3 py-2 text-xs font-semibold border border-slate-200 dark:border-slate-700 rounded-md text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                    Cetak Invoice
                </a>
                @endif
                <a href="{{ route('warehouse.stock.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                    ← Kembali ke Stok
                </a>
            </div>
        </div>
    </div>

    <!-- Barang Dikirim Card -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Daftar Barang Dikirim</h4>
            @if($transfer->isInTransit() && auth()->user()->hasPermission('warehouse_transfer.receive') && $canReceive)
            <span class="text-xs font-semibold text-amber-600 dark:text-amber-400 flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                <span>Menunggu Konfirmasi Penerima</span>
            </span>
            @endif
        </div>

        @if($transfer->isInTransit() && auth()->user()->hasPermission('warehouse_transfer.receive') && $canReceive)
        {{--
            Konfirmasi SEKALIGUS, bukan centang per barang (koreksi
            2026-09-18, keputusan eksplisit user) — checkbox per-SN/roll
            yang dulu dipasang 2026-09-04 buat anti-manipulasi kebalik jadi
            beban di kiriman ratusan/ribuan item. Kompensasinya: TIDAK ADA
            partial-receive lagi dari form ini (semua baris dispatch
            otomatis dianggap cocok 100% begitu ditekan, lihat
            `WarehouseTransferController::receive()`), dan gerbang terakhir
            sebelum submit adalah MODAL WARNING eksplisit (bukan basa-basi
            `confirm()` browser) yang minta staf udah cek fisik dulu.
            Selisih fisik yang ketauan belakangan tetap lewat jalur
            Adjustment/opname terpisah (kontrol-anti-manipulasi.md §7),
            BUKAN form ini.
        --}}
        <form id="transfer-receive-form" action="{{ route('warehouse.transfers.receive', $transfer) }}" method="POST">
            @csrf

            <div class="overflow-x-auto scroll-smooth">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang / Item</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Detail / Lot / SN</th>
                            <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah Dikirim</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                        @foreach($dispatchLines as $line)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $line->item->name }}</div>
                                <div class="text-[11px] font-mono text-slate-400">{{ $line->item->code }}</div>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-slate-600 dark:text-slate-300">
                                @if($line->serial)
                                    SN: {{ $line->serial->serial_number }}
                                @elseif($line->roll)
                                    Roll: {{ $line->roll->roll_code }}
                                @else
                                    {{ $line->lot_no ? 'Lot: '.$line->lot_no : '-' }}
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right font-mono text-sm font-semibold text-slate-700 dark:text-slate-300">
                                @if($line->serial)
                                    1 unit
                                @elseif($line->roll)
                                    {{ rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',') }} meter
                                @else
                                    {{ rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',') }} {{ $line->item->unit }}
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-5 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    Periksa fisik seluruh barang di atas dulu, baru tekan tombol ini — barang akan dianggap diterima 100% sesuai daftar.
                </p>
                <button type="button" x-data @click="$dispatch('open-modal', 'confirm-receive-transfer')" class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-xs shadow-emerald-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    <span>Konfirmasi & Terima Semua Barang</span>
                </button>
            </div>
        </form>

        <x-ui.modal name="confirm-receive-transfer" title="⚠ Peringatan Konfirmasi Penerimaan" maxWidth="md">
            <p class="text-sm text-slate-700 dark:text-slate-200">
                Anda akan mengonfirmasi penerimaan sebanyak <strong>{{ $dispatchLines->count() }} item</strong> dari <strong>{{ $transfer->fromPop->name }}</strong>.
            </p>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-3">
                Pastikan seluruh fisik barang sudah diperiksa dan sesuai dengan dokumen pengiriman (Surat Jalan). Tindakan ini akan langsung menambahkan stok ke {{ $transfer->toPop->name }} dan <strong>tidak dapat dibatalkan</strong>.
            </p>
            <x-slot name="footer">
                <button type="submit" form="transfer-receive-form" class="w-full sm:w-auto inline-flex justify-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-sm font-bold cursor-pointer">
                    Ya, Konfirmasi Terima Semua
                </button>
                <button type="button" @click="$dispatch('close-modal', 'confirm-receive-transfer')" class="w-full sm:w-auto inline-flex justify-center px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 rounded-lg text-sm font-semibold cursor-pointer">
                    Batal
                </button>
            </x-slot>
        </x-ui.modal>
        @else
        <div class="overflow-x-auto scroll-smooth">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang / Item</th>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Detail / Lot / SN</th>
                        <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah Dikirim</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                    @foreach($dispatchLines as $line)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $line->item->name }}</div>
                            <div class="text-[11px] font-mono text-slate-400">{{ $line->item->code }}</div>
                        </td>
                        <td class="px-6 py-4 font-mono text-xs text-slate-600 dark:text-slate-300">
                            @if($line->serial)
                                @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                <a href="{{ route('warehouse.traceability.index', ['sn' => $line->serial->serial_number]) }}" class="text-sky-600 dark:text-sky-400 font-bold hover:underline">
                                    SN: {{ $line->serial->serial_number }}
                                </a>
                                @else
                                <span class="font-bold">SN: {{ $line->serial->serial_number }}</span>
                                @endif
                            @elseif($line->roll)
                                @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                <a href="{{ route('warehouse.traceability.index', ['roll' => $line->roll->roll_code]) }}" class="text-amber-600 dark:text-amber-400 font-bold hover:underline">
                                    Roll: {{ $line->roll->roll_code }}
                                </a>
                                @else
                                <span class="font-bold">Roll: {{ $line->roll->roll_code }}</span>
                                @endif
                            @else
                                <span class="text-slate-400">{{ $line->lot_no ? "Lot: {$line->lot_no}" : '-' }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right font-mono text-sm font-bold text-slate-800 dark:text-slate-200">
                            @if($line->serial)
                                1 unit
                            @elseif($line->roll)
                                {{ rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',') }} meter
                            @else
                                {{ rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',') }} {{ $line->item->unit }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    <!-- Sudah Diterima Card (jika sudah dikonfirmasi) -->
    @if($confirmedLines->isNotEmpty())
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <h4 class="text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                <span>Barang Telah Diterima di Gudang Cabang</span>
            </h4>
        </div>
        <div class="overflow-x-auto scroll-smooth">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                <thead class="bg-slate-50 dark:bg-slate-800/60">
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang / Item</th>
                        <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Detail / Lot / SN</th>
                        <th class="px-6 py-3.5 text-right text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah Diterima</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-slate-800 divide-y divide-slate-100 dark:divide-slate-700/50">
                    @foreach($confirmedLines as $line)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-6 py-4">
                            <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $line->item->name }}</div>
                            <div class="text-[11px] font-mono text-slate-400">{{ $line->item->code }}</div>
                        </td>
                        <td class="px-6 py-4 font-mono text-xs text-slate-600 dark:text-slate-300">
                            @if($line->serial)
                                @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                <a href="{{ route('warehouse.traceability.index', ['sn' => $line->serial->serial_number]) }}" class="text-sky-600 dark:text-sky-400 font-bold hover:underline">
                                    SN: {{ $line->serial->serial_number }}
                                </a>
                                @else
                                <span class="font-bold">SN: {{ $line->serial->serial_number }}</span>
                                @endif
                            @elseif($line->roll)
                                @if(auth()->user()->hasPermission('warehouse_traceability.view'))
                                <a href="{{ route('warehouse.traceability.index', ['roll' => $line->roll->roll_code]) }}" class="text-amber-600 dark:text-amber-400 font-bold hover:underline">
                                    Roll: {{ $line->roll->roll_code }}
                                </a>
                                @else
                                <span class="font-bold">Roll: {{ $line->roll->roll_code }}</span>
                                @endif
                            @else
                                <span class="text-slate-400">{{ $line->lot_no ? "Lot: {$line->lot_no}" : '-' }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">
                            @if($line->serial)
                                1 unit
                            @elseif($line->roll)
                                {{ rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',') }} meter
                            @else
                                {{ rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',') }} {{ $line->item->unit }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

@vite(['resources/js/barcode-scan.js'])

@endsection
