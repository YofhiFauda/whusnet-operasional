@extends('layouts.app')

@section('title', 'Detail Tagihan ' . $invoice->invoice_number . ' - Whusnet Operasional')
@section('page_title', 'Detail Tagihan')
@section('breadcrumb_parent', 'Tagihan')
@section('breadcrumb_parent_url', route('invoices.belum-lunas'))

@section('content')
@php
    $badgeClass = match($invoice->invoice_status->value) {
        'lunas' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
        'sebagian' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
        'batal' => 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20',
        'tak_tertagih' => 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20',
        default => 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20',
    };

    $totalAmount = (float) $invoice->total_amount;
    $paidAmount = (float) $invoice->paid_amount;
    $remainingAmount = (float) $invoice->remaining_amount;
    $paidPercentage = $totalAmount > 0 ? min(100, round(($paidAmount / $totalAmount) * 100, 1)) : 0;
    $remainingPercentage = max(0, round(100 - $paidPercentage, 1));
    
    // Logika tombol kembali: default selalu ke /invoices/belum-lunas (bukan query semua tagihan /invoices).
    // Jika ada return_url atau perujuk adalah list tagihan / profil pelanggan, gunakan URL tersebut agar filter tidak hilang.
    $backUrl = route('invoices.belum-lunas');
    if (request()->filled('return_url')) {
        $backUrl = request('return_url');
    } else {
        $previousUrl = url()->previous();
        if ($previousUrl && $previousUrl !== url()->current()) {
            $prevPath = parse_url($previousUrl, PHP_URL_PATH);
            if ($prevPath === '/invoices/belum-lunas' || $prevPath === '/invoices/lunas' || str_starts_with($prevPath ?? '', '/customers/')) {
                $backUrl = $previousUrl;
            }
        }
    }

    $customerPhone = $invoice->customer->primary_phone ?? $invoice->customer->phone ?? '';
    if ($customerPhone && !str_starts_with($customerPhone, '62')) {
        if (str_starts_with($customerPhone, '0')) {
            $customerPhone = '62' . substr($customerPhone, 1);
        }
    }
    
    $waText = rawurlencode("Halo Sdr/i " . ($invoice->customer->full_name ?? '') . ", mengingatkan tagihan internet WHUSNET periode " . $invoice->billing_period . " (" . $invoice->invoice_number . ") memiliki sisa pembayaran sebesar Rp " . number_format($remainingAmount, 0, ',', '.') . ". Terima kasih.");
    $waUrl = $customerPhone ? "https://wa.me/{$customerPhone}?text={$waText}" : '#';
@endphp

<style>
    @media print {
        /*
         * Header/footer bawaan browser (tanggal, judul dokumen, URL
         * localhost:8000/...) dicetak di KOTAK MARGIN halaman, di luar
         * jangkauan selector mana pun. margin:0 menghapus kotaknya sekalian.
         * Jarak ke tepi kertas ditanggung `.print-only.p-8` — jangan ikut
         * dinolkan.
         */
        @page { margin: 0; }
        .no-print, header, sidebar, footer, nav, #toast, .modal, .mobile-action-bar { display: none !important; }
        .print-only { display: block !important; }
        .screen-only { display: none !important; }
        body { background: white !important; color: black !important; padding: 0 !important; }
    }
    @media screen {
        .print-only { display: none !important; }
    }
</style>

<!-- PRINT ONLY A4 FAKTUR INVOICE / KWITANSI SHEET -->
<div class="print-only p-8 bg-white text-slate-900 font-sans text-xs leading-normal">
    <!-- Header -->
    <div class="flex justify-between items-start border-b pb-4 mb-4 border-slate-300">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-slate-900">WHUSNET</h1>
            <p class="text-xs text-slate-600 font-medium">{{ $invoice->pop->name ?? 'Kantor Pusat' }} &bull; ISP Service</p>
            <p class="text-[10px] text-slate-500 mt-0.5">Website Billing ISP Internal</p>
        </div>
        <div class="text-right">
            <h2 class="text-base font-bold text-slate-900 uppercase tracking-wide">FAKTUR INVOICE / KWITANSI</h2>
            <p class="font-mono text-xs font-semibold text-slate-700">No: {{ $invoice->invoice_number }}</p>
            <p class="text-xs text-slate-600 mt-1">Status: <span class="font-bold uppercase text-emerald-700">● {{ $invoice->invoice_status->label() }}</span></p>
        </div>
    </div>

    <!-- Info Grid -->
    <div class="grid grid-cols-2 gap-6 mb-6 text-xs">
        <div>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">DITERBITKAN KEPADA</p>
            <p class="font-bold text-sm text-slate-900">{{ $invoice->customer->full_name ?? '-' }}</p>
            <p class="font-mono text-xs text-slate-700">CID: {{ $invoice->customer->cid ?? $invoice->customer->customer_code ?? '-' }}</p>
            <p class="text-slate-600 font-mono">No. HP: {{ $invoice->customer->primary_phone ?? $invoice->customer->phone ?? '-' }}</p>
            <p class="text-slate-600 mt-0.5">Alamat: {{ $invoice->customer->address ?? '-' }}</p>
        </div>
        <div class="text-right space-y-1">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">DETAIL TAGIHAN</p>
            <p><span class="text-slate-500">Tanggal Terbit:</span> <span class="font-semibold">{{ optional($invoice->issue_date)->format('d/m/Y') }}</span></p>
            <p><span class="text-slate-500">Jatuh Tempo:</span> <span class="font-semibold">{{ optional($invoice->due_date)->format('d/m/Y') }}</span></p>
            <p><span class="text-slate-500">Periode Tagihan:</span> <span class="font-mono font-semibold">{{ $invoice->billing_period }}</span></p>
            <p><span class="text-slate-500">POP / Cabang:</span> <span class="font-semibold">{{ $invoice->pop->name ?? '-' }}</span></p>
        </div>
    </div>

    <!-- Table Rincian Biaya -->
    <table class="w-full text-left border-collapse text-xs mb-6">
        <thead>
            <tr class="border-y border-slate-300 bg-slate-100 text-slate-700 uppercase font-semibold text-[10px]">
                <th class="py-2.5 px-3">Deskripsi Layanan</th>
                <th class="py-2.5 px-3 text-center">Periode</th>
                <th class="py-2.5 px-3 text-right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            {{--
                Rincian per kategori pendapatan (ADHOC-60). Tagihan yang terbit
                SEBELUM fitur ini tidak punya baris sama sekali, jadi seluruh
                blok lama di bawah dipertahankan sebagai fallback — jangan
                dihapus sampai backfill terbukti di produksi dan kolom biaya
                lama benar-benar dibuang.
            --}}
            @if($invoice->items->isNotEmpty())
                @foreach($invoice->items as $item)
                <tr>
                    <td class="py-3 px-3">
                        <p class="font-bold text-slate-900">{{ $item->subcategory_name_snapshot }}</p>
                        <p class="text-[11px] text-slate-500">
                            {{ $item->category_name_snapshot }}@if($item->description) — {{ $item->description }}@endif
                        </p>
                    </td>
                    <td class="py-3 px-3 text-center font-mono">{{ $loop->first ? $invoice->billing_period : '-' }}</td>
                    <td class="py-3 px-3 text-right font-mono font-bold">Rp {{ number_format((float) $item->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            @else
            <tr>
                <td class="py-3 px-3">
                    <p class="font-bold text-slate-900">{{ $invoice->customerService->package_name_snapshot ?? $invoice->internetPackage->name ?? 'Paket Internet' }}</p>
                    <p class="text-[11px] text-slate-500">Langganan Internet Dedicated — DL/UL: {{ $invoice->customerService->download_speed_snapshot ?? '-' }} / {{ $invoice->customerService->upload_speed_snapshot ?? '-' }}</p>
                </td>
                <td class="py-3 px-3 text-center font-mono">{{ $invoice->billing_period }}</td>
                <td class="py-3 px-3 text-right font-mono font-bold">Rp {{ number_format((float) $invoice->subtotal, 0, ',', '.') }}</td>
            </tr>
            @if((float)$invoice->prorate_amount > 0)
            <tr>
                <td class="py-2 px-3 text-slate-700">Tagihan Prorate Layanan</td>
                <td class="py-2 px-3 text-center font-mono">-</td>
                <td class="py-2 px-3 text-right font-mono font-semibold">Rp {{ number_format((float) $invoice->prorate_amount, 0, ',', '.') }}</td>
            </tr>
            @endif
            @if((float)$invoice->extra_cable_fee > 0)
            <tr>
                <td class="py-2 px-3 text-slate-700">Biaya Kabel Tambahan</td>
                <td class="py-2 px-3 text-center font-mono">-</td>
                <td class="py-2 px-3 text-right font-mono font-semibold">Rp {{ number_format((float) $invoice->extra_cable_fee, 0, ',', '.') }}</td>
            </tr>
            @endif
            @if((float)$invoice->other_fee > 0)
            <tr>
                <td class="py-2 px-3 text-slate-700">Biaya Lain-lain</td>
                <td class="py-2 px-3 text-center font-mono">-</td>
                <td class="py-2 px-3 text-right font-mono font-semibold">Rp {{ number_format((float) $invoice->other_fee, 0, ',', '.') }}</td>
            </tr>
            @endif
            @endif
        </tbody>
    </table>

    <!-- Calculation Breakdown & Signatures -->
    <div class="flex justify-between items-start gap-6 text-xs">
        <div class="space-y-2 max-w-xs">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">KETERANGAN & CATATAN</p>
            <p class="text-[11px] text-slate-600">Dokumen faktur/kwitansi ini diterbitkan secara resmi oleh sistem WHUSNET Operasional. Pembayaran melalui Kasir POP / Transfer Bank resmi.</p>
            <p class="text-[10px] text-slate-400 italic mt-4">Struk / Kwitansi sah tanpa tanda tangan &bull; Dicetak {{ now()->format('d/m/Y H:i') }}</p>
        </div>

        <div class="w-64 space-y-1.5 text-xs">
            <div class="flex justify-between text-slate-600">
                <span>Harga Paket (Subtotal)</span>
                <span class="font-mono font-semibold">Rp {{ number_format((float)$invoice->subtotal, 0, ',', '.') }}</span>
            </div>
            @if((float)$invoice->discount > 0)
            <div class="flex justify-between text-emerald-600 font-semibold">
                <span>Diskon</span>
                <span class="font-mono">- Rp {{ number_format((float)$invoice->discount, 0, ',', '.') }}</span>
            </div>
            @endif
            <div class="flex justify-between text-slate-600">
                <span>PPN</span>
                <span class="font-mono">{{ (float)$invoice->ppn > 0 ? 'Rp ' . number_format(((float)$invoice->subtotal - (float)$invoice->discount) * ((float)$invoice->ppn / 100), 0, ',', '.') : 'Tidak dikenakan' }}</span>
            </div>
            <div class="flex justify-between pt-2 border-t border-slate-300 font-bold text-slate-900 text-sm">
                <span>Total Tagihan</span>
                <span class="font-mono">Rp {{ number_format((float)$invoice->total_amount, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between text-emerald-600 font-semibold">
                <span>Sudah Terbayar</span>
                <span class="font-mono">Rp {{ number_format((float)$invoice->paid_amount, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between pt-1 border-t border-slate-200 font-bold text-slate-900">
                <span>Sisa Tagihan</span>
                <span class="font-mono {{ (float)$invoice->remaining_amount > 0 ? 'text-red-600' : 'text-emerald-600' }}">Rp {{ number_format((float)$invoice->remaining_amount, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
</div>

<!-- SCREEN ONLY ENTERPRISE REDESIGN VIEW -->
<div class="space-y-5 screen-only max-w-full pb-20 md:pb-8">
    
    <!-- LAYER 1: NAKED PAGE HEADER (Strict Design System Rule: No card wrapper) -->
    <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-4 mb-2">
        <div class="space-y-1.5 flex-1 min-w-0">
            <div class="flex items-center gap-2 sm:gap-2.5 flex-wrap">
                <a href="{{ $backUrl }}" class="p-1.5 text-slate-500 hover:text-slate-900 dark:hover:text-slate-100 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors" title="Kembali">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-slate-50 tracking-tight leading-tight">Detail Tagihan</h1>
                
                <!-- Invoice Technical ID Badge + Copy -->
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-mono font-medium text-slate-800 dark:text-slate-200">
                    <span>{{ $invoice->invoice_number }}</span>
                    <button type="button" onclick="copyToClipboard('{{ $invoice->invoice_number }}', 'No. Invoice')" class="text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 transition-colors cursor-pointer" title="Salin No. Invoice">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                </div>

                <!-- Status Badge -->
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badgeClass }}">
                    <span class="w-1.5 h-1.5 rounded-full bg-current {{ $invoice->invoice_status->value === 'sebagian' ? 'animate-pulse' : '' }}"></span>
                    {{ $invoice->invoice_status->label() }}
                </span>

                @if($invoice->invoice_type)
                <span class="px-2.5 py-0.5 text-xs font-medium rounded-full border bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800">
                    {{ $invoice->invoice_type->label() }}
                </span>
                @endif

                {{-- Sub-jenis Tagihan Manual (ADHOC-70/69) --}}
                @if($invoice->manual_subtype_name)
                <span class="px-2.5 py-0.5 text-xs font-medium rounded-full border bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800">
                    {{ $invoice->manual_subtype_name }}
                </span>
                @elseif($invoice->manual_category)
                <span class="px-2.5 py-0.5 text-xs font-medium rounded-full border bg-slate-100 dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700">
                    {{ $invoice->manual_category->label() }}
                </span>
                @endif

                @php
                    $invoiceTotalOverpay = $invoice->payments
                        ->filter(fn ($p) => $p->payment_status === \App\Enums\PaymentStatus::VALID)
                        ->sum(fn ($p) => (float) $p->overpay_amount);
                @endphp
                @if($invoiceTotalOverpay > 0)
                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full border bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-800" title="Total uang lebih yang diserahkan pelanggan pada invoice ini">
                        Lebih Bayar Rp {{ number_format($invoiceTotalOverpay, 0, ',', '.') }}
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-x-2.5 gap-y-1 flex-wrap text-xs text-slate-500 dark:text-slate-400">
                <span>Periode: <strong class="text-slate-800 dark:text-slate-200 font-mono">{{ $invoice->billing_period }}</strong></span>
                <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                <span>Diterbitkan {{ optional($invoice->issue_date)->format('d/m/Y') }} oleh {{ $invoice->creator->name ?? 'System' }}</span>
                @if($invoice->description)
                <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                <span>{{ $invoice->description }}</span>
                @endif
            </div>

            {{-- ADHOC-87 --}}
            @if($invoice->invoice_status->value === 'batal' && $invoice->billingWaiver)
            <div class="mt-2 px-3 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-rose-700 dark:text-rose-400">
                <span class="font-semibold">Dibatalkan ({{ $invoice->billingWaiver->source->label() }})</span> oleh {{ $invoice->billingWaiver->creator->name ?? 'System' }}
                pada {{ \App\Support\IndonesianDate::date($invoice->billingWaiver->created_at) }} — {{ $invoice->billingWaiver->reason }}
            </div>
            @endif
        </div>

        <!-- Desktop Action Buttons Toolbar -->
        <div class="flex items-center gap-2 shrink-0 flex-wrap w-full xl:w-auto pt-1 xl:pt-0 no-print">
            @if($customerPhone)
                <a href="{{ $waUrl }}" target="_blank" class="inline-flex items-center justify-center gap-1.5 px-3 sm:px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-emerald-700 dark:text-emerald-400 text-xs font-semibold rounded-lg transition-all shadow-2xs">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.105 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                    <span>WhatsApp</span>
                </a>
            @endif

            @if(auth()->user()->hasPermission('create_payments') && !in_array($invoice->invoice_status->value, ['lunas', 'batal', 'tak_tertagih'], true))
                <a href="{{ route('invoices.payments.create', $invoice->id) }}" class="inline-flex items-center justify-center gap-1.5 px-3 sm:px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-xs transition-all cursor-pointer">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <span>{{ $invoice->invoice_status->value === 'sebagian' ? 'Bayar Cicil' : 'Input Pembayaran' }}</span>
                </a>
            @endif

            <!-- Print Menu Dropdown -->
            <div class="relative">
                <button type="button" onclick="togglePrintDropdown(event)" id="printDropdownBtn" class="inline-flex items-center justify-center gap-1.5 px-3 sm:px-3.5 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700/50 text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-lg shadow-2xs transition-colors cursor-pointer">
                    <svg class="w-4 h-4 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak</span>
                    <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div id="printDropdownMenu" class="hidden absolute right-0 mt-1.5 w-56 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg py-1 z-40 text-xs">
                    <button type="button" onclick="window.print(); closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors text-left cursor-pointer font-medium">
                        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <div>
                            <p class="font-semibold text-slate-900 dark:text-slate-100 leading-tight">Cetak Invoice A4 (PDF)</p>
                            <p class="text-[10px] text-slate-500 dark:text-slate-400">Format dokumen faktur resmi</p>
                        </div>
                    </button>
                    <div class="border-t border-slate-200 dark:border-slate-700"></div>
                    @if($invoice->payments->count() > 0)
                        <a href="{{ route('payments.receipt', $invoice->payments->first()->id) }}" target="_blank" onclick="closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-800 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition-colors text-left font-medium">
                            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-slate-100 leading-tight">Cetak Kwitansi</p>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400">Kwitansi bukti bayar kasir POP</p>
                            </div>
                        </a>
                    @else
                        <button type="button" onclick="window.Toast.warning('Belum Ada Struk', 'Belum ada riwayat pembayaran terdaftar untuk mencetak struk kasir.'); closePrintDropdown();" class="w-full px-3.5 py-2.5 flex items-center gap-2.5 text-slate-400 dark:text-slate-500 hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors text-left opacity-75">
                            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                            <div>
                                <p class="font-semibold leading-tight">Cetak Kwitansi</p>
                                <p class="text-[10px] text-slate-400">Perlu pembayaran terdaftar</p>
                            </div>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Hapus buku piutang (ADHOC-90) --}}
    @php $canWriteOff = auth()->user()->hasPermission('invoices.approve'); @endphp
    @if($canWriteOff && $invoice->invoice_status === \App\Enums\InvoiceStatus::TAK_TERTAGIH)
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-3.5 sm:p-4 no-print shadow-2xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="text-xs text-slate-700 dark:text-slate-300">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800">● Tak Tertagih</span>
                        <p class="font-semibold text-slate-900 dark:text-slate-100">Piutang dihapus buku sebesar Rp {{ number_format((float) $invoice->written_off_amount, 0, ',', '.') }}</p>
                    </div>
                    <p class="mt-1 text-slate-500 dark:text-slate-400">{{ optional($invoice->written_off_at)->format('d/m/Y') }} — {{ $invoice->write_off_reason }}</p>
                </div>
                @php $writeOffLocked = \App\Support\BookPeriod::isLocked($invoice->written_off_at?->format('Y-m')); @endphp
                <form method="POST" action="{{ route('invoices.write-off.reverse', $invoice) }}" onsubmit="return confirm(@js($writeOffLocked ? 'Periode hapus buku ini sudah tutup buku. Tagihan tetap dikembalikan menjadi piutang dan pemulihannya dicatat di bulan ini; laporan bulan lama tidak berubah. Lanjutkan?' : 'Batalkan hapus buku? Tagihan kembali menjadi piutang.'))">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer">Batalkan Hapus Buku</button>
                </form>
            </div>
            @error('reason')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    @elseif($canWriteOff && $invoice->isPiutang())
        <div x-data="{ open: {{ $errors->has('reason') ? 'true' : 'false' }} }" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-3.5 sm:p-4 no-print shadow-2xs">
            <div class="flex items-center justify-between gap-3">
                <p class="text-xs text-slate-600 dark:text-slate-400">Piutang bulan lalu. Jika sudah tidak mungkin tertagih, tandai sebagai <strong class="text-slate-800 dark:text-slate-200 font-semibold">tak tertagih</strong>.</p>
                <button type="button" @click="open = !open" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition-colors cursor-pointer" x-text="open ? 'Tutup' : 'Hapus Buku'"></button>
            </div>
            <form x-show="open" x-cloak method="POST" action="{{ route('invoices.write-off', $invoice) }}" class="mt-3 space-y-2">
                @csrf
                <label class="block text-xs font-semibold text-slate-800 dark:text-slate-200" for="write_off_reason">Alasan hapus buku</label>
                <textarea id="write_off_reason" name="reason" rows="2" maxlength="500" required class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 text-xs p-2.5 focus:border-sky-500 focus:outline-none">{{ old('reason') }}</textarea>
                @error('reason')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="px-3.5 py-2 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-700 text-white transition-colors cursor-pointer">Hapus Buku Rp {{ number_format($remainingAmount, 0, ',', '.') }}</button>
            </form>
        </div>
    @endif

    <!-- LAYER 3: SINGLE UNIFIED DETAIL PANEL (Card Budget = 1) -->
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-2xs overflow-hidden">
        
        <!-- SECTION A: QUICK METRIC STRIP (Flat hairline divider grid inside panel) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-px bg-slate-200 dark:bg-slate-700 border-b border-slate-200 dark:border-slate-700">
            <!-- 1. Total Tagihan -->
            <div class="p-3.5 sm:p-4 bg-white dark:bg-slate-800 flex flex-col justify-center min-w-0">
                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">TOTAL TAGIHAN</span>
                <span class="text-base sm:text-lg font-bold font-mono text-slate-900 dark:text-slate-100 mt-0.5">Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Subtotal: Rp {{ number_format((float) $invoice->subtotal, 0, ',', '.') }}</span>
            </div>

            <!-- 2. Sisa Tagihan -->
            <div class="p-3.5 sm:p-4 bg-white dark:bg-slate-800 flex flex-col justify-center min-w-0">
                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">SISA TAGIHAN</span>
                <span class="text-base sm:text-lg font-bold font-mono mt-0.5 {{ $remainingAmount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                    Rp {{ number_format($remainingAmount, 0, ',', '.') }}
                </span>
                <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-mono mt-0.5">
                    Terbayar: Rp {{ number_format($paidAmount, 0, ',', '.') }}
                </span>
            </div>

            <!-- 3. Jatuh Tempo -->
            <div class="p-3.5 sm:p-4 bg-white dark:bg-slate-800 flex flex-col justify-center min-w-0">
                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">JATUH TEMPO</span>
                <span class="text-base sm:text-lg font-bold font-mono text-slate-900 dark:text-slate-100 mt-0.5">
                    {{ optional($invoice->due_date)->format('d/m/Y') ?? '-' }}
                </span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                    Terbit: {{ optional($invoice->issue_date)->format('d/m/Y') ?? '-' }}
                </span>
            </div>

            <!-- 4. POP / Cabang -->
            <div class="p-3.5 sm:p-4 bg-white dark:bg-slate-800 flex flex-col justify-center min-w-0">
                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">POP / CABANG</span>
                <span class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100 mt-0.5 truncate" title="{{ $invoice->pop->name ?? '-' }}">
                    {{ $invoice->pop->name ?? '-' }}
                </span>
                <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate">
                    Oleh: {{ $invoice->creator->name ?? 'System' }}
                </span>
            </div>
        </div>

        <!-- SECTION B: MAIN 2-COLUMN SPLIT (Left: Tabs & Content, Right: Identity & Service) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-slate-200 dark:divide-slate-700">
            
            <!-- LEFT MAIN COLUMN: TAB NAVIGATION & TAB PANES -->
            <div class="lg:col-span-7 xl:col-span-8 flex flex-col min-w-0">
                
                <!-- Tab Navigation Header -->
                <div class="p-2 sm:p-2.5 bg-slate-50/80 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 flex gap-1.5 overflow-x-auto no-print">
                    <button type="button" onclick="switchTab('items')" id="tab-items" class="px-3.5 py-2 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 text-sky-600 dark:text-sky-400 shadow-xs border border-slate-200/80 dark:border-slate-600/80 flex items-center gap-2 transition-all cursor-pointer shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Rincian Kalkulasi Biaya</span>
                    </button>

                    <button type="button" onclick="switchTab('payments')" id="tab-payments" class="px-3.5 py-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-white/60 dark:hover:bg-slate-700/60 flex items-center gap-2 transition-all cursor-pointer shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span>Riwayat Pembayaran ({{ $invoice->payments->count() }})</span>
                    </button>

                    @if($invoice->old_invoice_id || $invoice->old_cost_id || $invoice->old_request_id)
                    <button type="button" onclick="switchTab('audit')" id="tab-audit" class="px-3.5 py-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-white/60 dark:hover:bg-slate-700/60 flex items-center gap-2 transition-all cursor-pointer shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span>Audit Migrasi Legacy</span>
                    </button>
                    @endif
                </div>

                <!-- TAB PANE 1: Rincian Biaya -->
                <div id="pane-items" class="p-5 sm:p-6 space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-2.5">
                        <h3 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Rincian Komponen Invoice</h3>
                        <span class="text-xs font-mono text-slate-500 dark:text-slate-400">ID: {{ $invoice->id }}</span>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between items-center py-2 border-b border-slate-200 dark:border-slate-700">
                            <div class="space-y-0.5">
                                <p class="font-semibold text-slate-900 dark:text-slate-100">Harga Paket Internet (Subtotal)</p>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $invoice->customerService->package_name_snapshot ?? $invoice->internetPackage->name ?? 'Paket Internet' }} &bull; Periode {{ $invoice->billing_period }}</p>
                            </div>
                            <span class="font-mono font-bold text-slate-900 dark:text-slate-100 text-sm">Rp {{ number_format((float) $invoice->subtotal, 0, ',', '.') }}</span>
                        </div>

                        @if((float)$invoice->discount > 0)
                        <div class="flex justify-between items-center py-2 border-b border-slate-200 dark:border-slate-700 text-emerald-600 dark:text-emerald-400">
                            <span class="font-medium">Potongan Diskon</span>
                            <span class="font-mono font-semibold text-sm">- Rp {{ number_format((float) $invoice->discount, 0, ',', '.') }}</span>
                        </div>
                        @endif

                        @php
                            $afterDiscount = max(0, (float)$invoice->subtotal - (float)$invoice->discount);
                            $ppnRate = (float)$invoice->ppn;
                            $ppnAmount = round($afterDiscount * ($ppnRate / 100), 2);
                        @endphp

                        @if($ppnRate > 0)
                        <div class="flex justify-between items-center py-2 border-b border-slate-200 dark:border-slate-700">
                            <span class="text-slate-600 dark:text-slate-300 font-medium">PPN ({{ number_format($ppnRate, 0) }}%)</span>
                            <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 text-sm">Rp {{ number_format($ppnAmount, 0, ',', '.') }}</span>
                        </div>
                        @else
                        <div class="flex justify-between items-center py-2 border-slate-200 dark:border-slate-700">
                            <span class="text-slate-600 dark:text-slate-300 font-medium">PPN (Pajak Pertambahan Nilai)</span>
                            <span class="font-mono text-slate-500 dark:text-slate-400">Tidak dikenakan</span>
                        </div>
                        @endif

                        @if($invoice->items->isEmpty())
                        @if((float)($invoice->prorate_amount ?? 0) > 0)
                        <div class="flex justify-between items-center py-2 border-b border-slate-200 dark:border-slate-700">
                            <span class="text-slate-600 dark:text-slate-300 font-medium">Tagihan Prorate</span>
                            <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 text-sm">Rp {{ number_format((float) $invoice->prorate_amount, 0, ',', '.') }}</span>
                        </div>
                        @endif

                        @if((float)($invoice->extra_cable_fee ?? 0) > 0)
                        <div class="flex justify-between items-center py-2 border-b border-slate-200 dark:border-slate-700">
                            <span class="text-slate-600 dark:text-slate-300 font-medium">Biaya Kabel Tambahan</span>
                            <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 text-sm">Rp {{ number_format((float) $invoice->extra_cable_fee, 0, ',', '.') }}</span>
                        </div>
                        @endif

                        @if((float)($invoice->other_fee ?? 0) > 0)
                        <div class="flex justify-between items-center py-2 border-b border-slate-200 dark:border-slate-700">
                            <span class="text-slate-600 dark:text-slate-300 font-medium">Biaya Lain-lain</span>
                            <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 text-sm">Rp {{ number_format((float) $invoice->other_fee, 0, ',', '.') }}</span>
                        </div>
                        @endif
                        @endif

                        <!-- Summary Footer Breakdown -->
                        <div class="pt-4 space-y-2 border-t border-slate-200 dark:border-slate-700">
                            <div class="flex justify-between items-center text-xs font-semibold">
                                <span class="text-slate-600 dark:text-slate-300">TOTAL TAGIHAN</span>
                                <span class="font-mono text-sm font-bold text-slate-900 dark:text-slate-100">Rp {{ number_format($totalAmount, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between items-center text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                                <span>Sudah Terbayar</span>
                                <span class="font-mono text-sm">- Rp {{ number_format($paidAmount, 0, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between items-center text-sm font-bold pt-2 border-t border-slate-200 dark:border-slate-700">
                                <span class="{{ $remainingAmount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    {{ $remainingAmount > 0 ? 'SISA YANG HARUS DIBAYAR' : 'STATUS TAGIHAN LUNAS' }}
                                </span>
                                <span class="font-mono text-base font-bold {{ $remainingAmount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                    Rp {{ number_format($remainingAmount, 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB PANE 2: Riwayat Pembayaran -->
                <div id="pane-payments" class="hidden">
                    @if($invoice->payments->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-800/80 border-b border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] font-bold">
                                        <th class="px-4 py-3">Cicilan</th>
                                        <th class="px-4 py-3">No. Pembayaran</th>
                                        <th class="px-4 py-3">Tanggal</th>
                                        <th class="px-4 py-3">Metode</th>
                                        <th class="px-4 py-3 text-right">Nominal</th>
                                        <th class="px-4 py-3 text-center">Status</th>
                                        <th class="px-4 py-3">Penerima</th>
                                        <th class="px-4 py-3 text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700 text-slate-600 dark:text-slate-300">
                                    @foreach($invoice->payments as $payment)
                                        @php $meta = $payment->installmentContext(); @endphp
                                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                                            <td class="px-4 py-3 font-semibold text-slate-900 dark:text-slate-100">
                                                @if($meta)
                                                    Cicilan Ke-{{ $meta['number'] }}
                                                @else
                                                    &mdash;
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 font-mono font-bold text-sky-600 dark:text-sky-400">
                                                <a href="{{ route('payments.show', $payment->id) }}" class="hover:underline">
                                                    {{ $payment->payment_number }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 font-mono text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ optional($payment->payment_date)->format('d/m/Y') }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @php $paymentMethodEnum = \App\Enums\PaymentMethod::tryFrom((string) $payment->payment_method); @endphp
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase border {{ $paymentMethodEnum === \App\Enums\PaymentMethod::SALDO ? 'bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 border-sky-200 dark:border-sky-800' : 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' }}">
                                                    {{ $paymentMethodEnum === \App\Enums\PaymentMethod::SALDO ? 'Dibayar dari Saldo' : strtoupper((string) $payment->payment_method) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-slate-100 whitespace-nowrap">
                                                Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}
                                                @if((float) $payment->overpay_amount > 0)
                                                    <span class="block text-[10px] font-semibold text-amber-600 dark:text-amber-400" title="Uang lebih yang diserahkan pelanggan">
                                                        +{{ number_format((float) $payment->overpay_amount, 0, ',', '.') }} lebih
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                                @if(! $meta)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">Dikembalikan</span>
                                                @elseif($meta['settles'])
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">Lunas</span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">Cicil</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100 whitespace-nowrap">{{ $payment->receiver->name ?? '-' }}</td>
                                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    @if($payment->proof_file)
                                                        <a href="{{ asset('storage/' . $payment->proof_file) }}" target="_blank" class="px-2 py-1 bg-slate-100 dark:bg-slate-700 text-sky-600 dark:text-sky-400 hover:bg-slate-200 dark:hover:bg-slate-600 rounded text-[11px] font-semibold">Bukti</a>
                                                    @endif
                                                    <a href="{{ route('payments.receipt', $payment->id) }}" target="_blank" class="p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500 dark:text-slate-400 transition-colors" title="Lihat Kwitansi">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                        </svg>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-8 text-center text-xs text-slate-500 dark:text-slate-400">
                            Belum ada riwayat pembayaran yang dicatat untuk tagihan ini.
                        </div>
                    @endif
                </div>

                <!-- TAB PANE 3: Audit Migrasi Legacy (Conditional) -->
                @if($invoice->old_invoice_id || $invoice->old_cost_id || $invoice->old_request_id)
                <div id="pane-audit" class="hidden p-5 sm:p-6 space-y-4">
                    <h3 class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Audit Visibilitas Data Migrasi Legacy</h3>
                    
                    <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-slate-200 dark:border-slate-700 space-y-2 text-xs max-w-md">
                        @if($invoice->old_invoice_id)
                        <div class="flex justify-between items-center pb-2 border-b border-slate-200 dark:border-slate-700">
                            <span class="text-slate-500 dark:text-slate-400">ID Invoice Lama:</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-slate-100">{{ $invoice->old_invoice_id }}</span>
                        </div>
                        @endif
                        @if($invoice->old_cost_id)
                        <div class="flex justify-between items-center pb-2 border-b border-slate-200 dark:border-slate-700">
                            <span class="text-slate-500 dark:text-slate-400">ID Biaya Lama:</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-slate-100">{{ $invoice->old_cost_id }}</span>
                        </div>
                        @endif
                        @if($invoice->old_request_id)
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500 dark:text-slate-400">ID Permintaan Lama:</span>
                            <span class="font-mono font-bold text-slate-900 dark:text-slate-100">{{ $invoice->old_request_id }}</span>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            <!-- RIGHT COLUMN: CUSTOMER IDENTITY & PACKAGE INFO (Flat rows with divider) -->
            <div class="lg:col-span-5 xl:col-span-4 divide-y divide-slate-200 dark:divide-slate-700 bg-slate-50/30 dark:bg-slate-800/30">
                
                <!-- SECTION 1: IDENTITAS PELANGGAN -->
                <div class="p-5 sm:p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Identitas Pelanggan</span>
                        <a href="{{ route('customers.show', $invoice->customer_id) }}" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1">
                            <span>Profil Full</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/50 text-sky-700 dark:text-sky-300 font-bold text-sm flex items-center justify-center shrink-0 border border-sky-200 dark:border-sky-800">
                            {{ strtoupper(substr($invoice->customer->full_name ?? 'P', 0, 2)) }}
                        </div>
                        <div class="space-y-0.5 min-w-0">
                            <a href="{{ route('customers.show', $invoice->customer_id) }}" class="font-bold text-slate-900 dark:text-slate-100 text-sm hover:text-sky-600 dark:hover:text-sky-400 transition-colors block truncate">
                                {{ $invoice->customer->full_name ?? '-' }}
                            </a>
                            <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 font-mono">
                                <span>CID: {{ $invoice->customer->cid ?? $invoice->customer->customer_code ?? '-' }}</span>
                                @if($invoice->customer->cid || $invoice->customer->customer_code)
                                    <button type="button" onclick="copyToClipboard('{{ $invoice->customer->cid ?? $invoice->customer->customer_code }}', 'CID')" class="hover:text-sky-600 dark:hover:text-sky-400 transition-colors cursor-pointer" title="Salin CID">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs pt-2 border-t border-dashed border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                        <div class="flex justify-between gap-2">
                            <span class="text-slate-500 dark:text-slate-400">No. Telephone:</span>
                            <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">{{ $invoice->customer->primary_phone ?? $invoice->customer->phone ?? '-' }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-slate-500 dark:text-slate-400">POP / Cabang:</span>
                            <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $invoice->pop->name ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400 block mb-1">Alamat Pelanggan:</span>
                            <p class="p-2.5 rounded-lg bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 text-[11px] leading-relaxed">
                                {{ $invoice->customer->address ?? '-' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: PAKET LAYANAN -->
                <div class="p-5 sm:p-6 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Paket Layanan</span>
                        <span class="p-1 rounded-md bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </span>
                    </div>

                    <div class="space-y-2.5 text-xs">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Nama Paket</span>
                            <p class="font-bold text-slate-900 dark:text-slate-100 text-sm mt-0.5">
                                {{ $invoice->customerService->package_name_snapshot ?? $invoice->internetPackage->name ?? 'Paket Internet ISP' }}
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-1">
                            <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700">
                                <span class="text-[10px] text-slate-400 dark:text-slate-500 block">Speed DL / UL</span>
                                <span class="font-mono font-bold text-slate-900 dark:text-slate-100 text-xs mt-0.5 block">
                                    {{ $invoice->customerService->download_speed_snapshot ?? '-' }} / {{ $invoice->customerService->upload_speed_snapshot ?? '-' }}
                                </span>
                            </div>
                            <div class="p-2.5 rounded-lg bg-white dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700">
                                <span class="text-[10px] text-slate-400 dark:text-slate-500 block">Harga Langganan</span>
                                <span class="font-mono font-bold text-slate-900 dark:text-slate-100 text-xs mt-0.5 block">
                                    Rp {{ number_format((float) ($invoice->customerService->monthly_price ?? $invoice->subtotal), 0, ',', '.') }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- STICKY MOBILE ACTION BAR (FOR MOBILE USERS) -->
<div class="fixed bottom-0 inset-x-0 z-30 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 p-3 flex items-center gap-2 sm:hidden no-print mobile-action-bar">
    @if($customerPhone)
        <a href="{{ $waUrl }}" target="_blank" class="p-2.5 bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 rounded-lg border border-emerald-200 dark:border-emerald-800 flex items-center justify-center shrink-0" title="Kirim WA">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.105 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
        </a>
    @endif

    @if(auth()->user()->hasPermission('create_payments') && !in_array($invoice->invoice_status->value, ['lunas', 'batal', 'tak_tertagih'], true))
        <a href="{{ route('invoices.payments.create', $invoice->id) }}" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-lg shadow-xs flex items-center justify-center gap-1.5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            <span>{{ $invoice->invoice_status->value === 'sebagian' ? 'Bayar Cicil' : 'Bayar Tagihan' }}</span>
        </a>
    @endif
</div>

<script>
    function togglePrintDropdown(e) {
        if (e) e.stopPropagation();
        const menu = document.getElementById('printDropdownMenu');
        if (menu) menu.classList.toggle('hidden');
    }

    function closePrintDropdown() {
        const menu = document.getElementById('printDropdownMenu');
        if (menu) menu.classList.add('hidden');
    }

    document.addEventListener('click', function(e) {
        const btn = document.getElementById('printDropdownBtn');
        const menu = document.getElementById('printDropdownMenu');
        if (menu && !menu.classList.contains('hidden') && !menu.contains(e.target) && !btn?.contains(e.target)) {
            closePrintDropdown();
        }
    });

    function switchTab(tabKey) {
        const tabs = ['items', 'payments', 'audit'];
        tabs.forEach(key => {
            const btn = document.getElementById(`tab-${key}`);
            const pane = document.getElementById(`pane-${key}`);
            if (!btn || !pane) return;
            
            if (key === tabKey) {
                btn.className = 'px-3.5 py-2 rounded-lg text-xs font-semibold bg-white dark:bg-slate-700 text-sky-600 dark:text-sky-400 shadow-xs border border-slate-200/80 dark:border-slate-600/80 flex items-center gap-2 transition-all cursor-pointer shrink-0';
                pane.classList.remove('hidden');
            } else {
                btn.className = 'px-3.5 py-2 rounded-lg text-xs font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-white/60 dark:hover:bg-slate-700/60 flex items-center gap-2 transition-all cursor-pointer shrink-0';
                pane.classList.add('hidden');
            }
        });
    }

    function copyToClipboard(text, label) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            const input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        }
        if (window.Toast) {
            window.Toast.success('Disalin', `${label} (${text}) berhasil disalin!`);
        }
    }
</script>
@endsection
