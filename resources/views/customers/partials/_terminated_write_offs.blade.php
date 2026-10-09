{{-- Kolom Tagihan List Putus Langganan (ADHOC-105): tagihan tak tertagih milik
     pelanggan (hasil hapus buku otomatis setelah masa tenggang habis, atau
     manual). Rancangan: docs/plan/billing/rancangan-piutang-tak-tertagih-otomatis-pelanggan-putus.md §3.4.

     Dialognya <dialog>.showModal() (top layer), BUKAN div fixed: halaman ini
     dibungkus `@container` yang menerapkan layout containment, dan elemen
     `position: fixed` di dalamnya jadi terkurung di kotak itu (terpotong
     overflow-hidden), bukan memenuhi layar. Dialog view-only (pola 1 CLAUDE.md);
     tombol Kembalikan tetap form POST biasa dengan action dirender server-side
     lewat route().

     Variabel: $customer (dengan ->tak_tertagih_invoices dari RendersCustomerList). --}}
@php
    $writtenOffInvoices = $customer->tak_tertagih_invoices ?? collect();
    $writtenOffTotal = \App\Support\Money::sum($writtenOffInvoices->map(fn ($inv) => $inv->written_off_amount ?? $inv->remaining_amount));
    $canReverseWriteOff = auth()->user()->hasPermission('invoices.approve');
@endphp

@if($writtenOffInvoices->isEmpty())
    <span class="text-slate-400">-</span>
@else
    <div x-data>
        <button type="button" @click="$refs.writeOffDialog.showModal()"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold rounded-full border bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/60 hover:bg-rose-100 dark:hover:bg-rose-950/60 transition-colors cursor-pointer">
            {{ $writtenOffInvoices->count() }} tagihan · {{ format_rupiah($writtenOffTotal) }}
        </button>

        <dialog x-ref="writeOffDialog" @click.self="$refs.writeOffDialog.close()"
                class="w-[min(42rem,calc(100vw-2rem))] max-h-[85vh] m-auto rounded-2xl p-0 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-800 shadow-xl backdrop:bg-black/50">
            <div class="flex flex-col max-h-[85vh] text-left normal-case tracking-normal">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Tagihan Tak Tertagih</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">{{ $customer->full_name }} — {{ $customer->display_id }}</p>
                    </div>
                    <button type="button" @click="$refs.writeOffDialog.close()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 text-lg leading-none cursor-pointer" aria-label="Tutup">&times;</button>
                </div>

                <div class="px-5 py-4 overflow-y-auto space-y-3">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Tagihan ini dikeluarkan dari tab Tagihan karena masa tenggang setelah putus langganan habis. Utangnya masih ada:
                        <strong class="text-slate-700 dark:text-slate-200">Langganan Lagi baru bisa dilakukan setelah tagihan dikembalikan dan dilunasi.</strong>
                    </p>

                    <ul class="divide-y divide-slate-100 dark:divide-slate-800 border border-slate-200 dark:border-slate-800 rounded-xl">
                        @foreach($writtenOffInvoices as $writtenOffInvoice)
                            <li class="px-3.5 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <div class="min-w-0 text-xs">
                                    <a href="{{ route('invoices.show', $writtenOffInvoice) }}" class="font-mono font-semibold text-sky-600 dark:text-sky-400 hover:underline">{{ $writtenOffInvoice->invoice_number }}</a>
                                    <span class="text-slate-400">·</span>
                                    <span>{{ $writtenOffInvoice->invoice_type?->label() }}</span>
                                    <p class="mt-0.5 text-slate-500 dark:text-slate-400">
                                        {{ format_rupiah($writtenOffInvoice->written_off_amount ?? $writtenOffInvoice->remaining_amount) }}
                                        · dihapus buku {{ optional($writtenOffInvoice->written_off_at)->format('d/m/Y') }}
                                    </p>
                                    @if($writtenOffInvoice->write_off_reason)
                                        <p class="mt-0.5 text-[11px] text-slate-400">{{ $writtenOffInvoice->write_off_reason }}</p>
                                    @endif
                                </div>
                                @if($canReverseWriteOff)
                                    <form action="{{ route('invoices.write-off.reverse', $writtenOffInvoice) }}" method="POST" class="shrink-0"
                                          onsubmit="event.preventDefault(); window.confirmAction(@js('Kembalikan tagihan '.$writtenOffInvoice->invoice_number.' ke tab Tagihan?'), this);">
                                        @csrf
                                        <input type="hidden" name="redirect_to" value="customers.terminated">
                                        <button type="submit" class="px-2.5 py-1 text-xs font-medium text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors cursor-pointer">
                                            Kembalikan
                                        </button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="px-5 py-3.5 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-2">
                    <button type="button" @click="$refs.writeOffDialog.close()" class="px-3 py-1.5 text-xs font-medium text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer">Tutup</button>
                    @if($canReverseWriteOff)
                        <form action="{{ route('customers.write-off.reverse-all', $customer) }}" method="POST"
                              onsubmit="event.preventDefault(); window.confirmAction(@js('Kembalikan SEMUA '.$writtenOffInvoices->count().' tagihan '.$customer->full_name.' ke tab Tagihan?'), this);">
                            @csrf
                            <button type="submit" class="px-3 py-1.5 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-lg cursor-pointer">Kembalikan Semua</button>
                        </form>
                    @endif
                </div>
            </div>
        </dialog>
    </div>
@endif
