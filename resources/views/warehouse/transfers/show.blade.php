@extends('layouts.app')

@section('title', 'Transfer '.$transfer->reference_number.' - Whusnet Operasional')
@section('page_title', 'Transfer '.$transfer->reference_number)

@section('content')

<x-warehouse.header active="stock" title="Surat Jalan Transfer #{{ $transfer->reference_number }}" subtitle="Dokumen perpindahan material & perangkat antar gudang POP Whusnet." />

<div class="space-y-6">
    <!-- Header Status Card -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-6 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3.5">
                <div class="w-11 h-11 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-800/60 shadow-xs shrink-0">
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
                <a href="{{ route('warehouse.stock.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100">
                    ← Kembali ke Stok
                </a>
            </div>
        </div>
    </div>

    <!-- Barang Dikirim Card -->
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
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
        @php
            $expectedSerials = $dispatchLines->pluck('serial.serial_number')->filter()->values();
        @endphp
        <form action="{{ route('warehouse.transfers.receive', $transfer) }}" method="POST"
              x-data="{
                confirmed: [],
                mismatches: [],
                onScan(code) {
                    if (this.confirmed.includes(code)) return; // udah dicentang (scan dobel/masih di depan kamera), gak perlu apa-apa lagi.
                    if (@js($expectedSerials).includes(code)) {
                        this.confirmed.push(code);
                        window.Toast?.success('SN Cocok', `'${code}' ada di daftar kiriman — dicentang.`, 2500);
                    } else {
                        if (! this.mismatches.includes(code)) {
                            this.mismatches.push(code);
                        }
                        window.Toast?.warning('SN Tidak Ada di Daftar Kiriman', `'${code}' bukan bagian transfer ini — JANGAN diterima, laporkan ke Gudang Pusat.`);
                    }
                },
                clearMismatches() { this.mismatches = []; },
              }"
              @barcode-detected.window="$event.detail.target === 'transfer-receive' && onScan($event.detail.code)">
            @csrf

            {{--
                Scan Kamera — konfirmasi FISIK per unit (2026-09-04, celah
                anti-manipulasi). SEBELUMNYA checkbox default TERCENTANG
                SEMUA ("percaya sistem", bisa di-konfirmasi tanpa
                benar-benar cek fisik satu-satu) — sekarang default KOSONG,
                cuma ke-centang lewat SCAN (atau centang manual) per unit.
                SN yang di-scan tapi TIDAK ADA di daftar kiriman ini
                ditampilkan sebagai mismatch — ketahuan SAAT ITU JUGA
                (barang ketuker/gak sesuai surat jalan), bukan nanti pas
                opname.
            --}}
            @if($expectedSerials->isNotEmpty())
            <div class="px-6 pt-5">
                <x-warehouse.barcode-scanner target="transfer-receive" />

                <div x-show="mismatches.length > 0" x-cloak class="mt-3 bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 rounded-xl p-3.5">
                    <div class="flex items-center justify-between mb-1.5">
                        <p class="text-xs font-bold text-rose-800 dark:text-rose-300">⚠ SN Terscan TIDAK ADA di Daftar Kiriman Ini</p>
                        <button type="button" @click="clearMismatches()" class="text-[11px] font-semibold text-rose-500 hover:text-rose-600 cursor-pointer">Tutup</button>
                    </div>
                    <p class="text-[11px] text-rose-700/90 dark:text-rose-400 mb-2">Barang ketuker dari transfer lain, atau bukan bagian pengiriman ini — JANGAN diterima, laporkan ke Gudang Pusat sebelum konfirmasi.</p>
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="code in mismatches" :key="code">
                            <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-full bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800" x-text="code"></span>
                        </template>
                    </div>
                </div>
            </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Barang / Item</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Detail / Lot / SN</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Jumlah Dikirim</th>
                            <th class="px-6 py-3.5 text-left text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Konfirmasi Terima Fisik</th>
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
                                {{ $line->serial ? 'SN: '.$line->serial->serial_number : ($line->lot_no ? 'Lot: '.$line->lot_no : '-') }}
                            </td>
                            <td class="px-6 py-4 font-mono text-sm font-semibold text-slate-700 dark:text-slate-300">
                                {{ $line->serial ? '1 unit' : rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',').' '.$line->item->unit }}
                            </td>
                            <td class="px-6 py-4">
                                @if($line->serial)
                                <label class="inline-flex items-center gap-2 text-xs font-semibold px-3 py-1.5 rounded-xl border cursor-pointer transition-colors"
                                       :class="confirmed.includes('{{ $line->serial->serial_number }}') ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800' : 'text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700'">
                                    <input type="checkbox" name="confirmed_serial_numbers[]" value="{{ $line->serial->serial_number }}" x-model="confirmed" class="rounded text-emerald-600 focus:ring-emerald-500">
                                    <span x-text="confirmed.includes('{{ $line->serial->serial_number }}') ? 'Cocok & Diterima' : 'Scan / centang buat konfirmasi'"></span>
                                </label>
                                @elseif($line->lot_no)
                                <div class="flex items-center gap-1.5">
                                    <input type="number" step="0.01" min="0" name="confirmed_quantities[{{ $line->item_id }}][{{ $line->lot_no }}]" value="{{ $line->qty }}"
                                        class="w-32 text-xs font-mono font-bold px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                    <span class="text-xs text-slate-400">{{ $line->item->unit }}</span>
                                </div>
                                @else
                                <div class="flex items-center gap-1.5">
                                    <input type="number" step="0.01" min="0" name="confirmed_quantities[{{ $line->item_id }}]" value="{{ $line->qty }}"
                                        class="w-32 text-xs font-mono font-bold px-3 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500">
                                    <span class="text-xs text-slate-400">{{ $line->item->unit }}</span>
                                </div>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-5 border-t border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    Scan tiap unit yang fisik nyampe (otomatis tercentang), atau centang manual kalau gak ada kamera. SN yang gak dicentang dianggap TIDAK diterima. Koreksi jumlah Qty kalau ada selisih.
                </p>
                <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-emerald-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    <span>Konfirmasi Penerimaan Transfer</span>
                </button>
            </div>
        </form>
        @else
        <div class="overflow-x-auto">
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
                            @else
                                <span class="text-slate-400">{{ $line->lot_no ? "Lot: {$line->lot_no}" : '-' }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right font-mono text-sm font-bold text-slate-800 dark:text-slate-200">
                            {{ $line->serial ? '1 unit' : rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',').' '.$line->item->unit }}
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
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
        <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <h4 class="text-xs font-bold text-emerald-700 dark:text-emerald-400 uppercase tracking-wider flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                <span>Barang Telah Diterima di Gudang Cabang</span>
            </h4>
        </div>
        <div class="overflow-x-auto">
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
                            @else
                                <span class="text-slate-400">{{ $line->lot_no ? "Lot: {$line->lot_no}" : '-' }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400">
                            {{ $line->serial ? '1 unit' : rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ',').' '.$line->item->unit }}
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
