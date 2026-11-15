{{--
    Rincian baris tagihan (invoice_items, ADHOC-60) untuk tampilan LAYAR —
    dipakai Detail Tagihan (tab Rincian) dan Detail Pembayaran. Dulu baris ini
    cuma dirender di versi cetak invoice; di layar cuma tampil satu angka
    subtotal berlabel "Harga Paket", jadi Biaya Pemasangan / Pembelian Modem /
    Prorata di Tagihan Pertama tidak kelihatan (laporan user 2026-11-14).

    Variabel: $invoice (Invoice, relasi items) lewat @include. Tagihan lama tanpa baris
    (sebelum ADHOC-60) tidak dirender di sini — pemanggil yang menangani
    fallback-nya.
--}}

<div class="divide-y divide-slate-200 dark:divide-slate-700">
    @foreach($invoice->items as $item)
        <div class="flex justify-between items-start gap-4 py-2">
            <div class="space-y-0.5 min-w-0">
                <p class="font-semibold text-slate-900 dark:text-slate-100">{{ $item->subcategory_name_snapshot }}</p>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                    {{ $item->category_name_snapshot }}@if($item->description) — {{ $item->description }}@endif
                </p>
            </div>
            <span class="font-mono font-semibold text-slate-900 dark:text-slate-100 text-sm shrink-0 tabular-nums">Rp {{ number_format((float) $item->amount, 0, ',', '.') }}</span>
        </div>
    @endforeach
</div>
