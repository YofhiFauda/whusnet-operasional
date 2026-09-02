{{--
    Tabel tagihan + input bayar per baris — dipakai Worksheet Admin dan
    Worklist Kolektor. Satu markup untuk dua halaman supaya dua audiens
    melihat angka & aturan yang sama persis (§9); yang beda cuma endpoint
    tujuannya, lihat partials/collector-pay-script.

    Variabel wajib: $invoices (paginator), $emptyMessage.
    Baris diurutkan per pelanggan di CollectorWorklistService, jadi tagihan
    pelanggan yang sama selalu berdempet — sekali datang, selesai semua.
--}}
<div class="bg-slate-50/50 dark:bg-slate-900/50 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs max-w-full">
    {{-- Header "Pilih Semua" versi Mobile/Tablet/Laptop-1024 --}}
    @if ($invoices->isNotEmpty())
        <div class="xl:hidden flex items-center justify-between px-3.5 py-2.5 bg-slate-100/80 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-700/80 text-xs">
            <label class="flex items-center gap-2 cursor-pointer select-none font-semibold text-slate-700 dark:text-slate-200">
                <input type="checkbox" id="cb-select-all-mobile" onclick="cbToggleAll(this)" class="rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sky-600 focus:ring-sky-500 cursor-pointer w-4 h-4">
                <span>Pilih Semua Pelanggan</span>
            </label>
            <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono font-medium">
                {{ $invoices->total() ?? $invoices->count() }} Tagihan
            </span>
        </div>
    @endif

    <div class="overflow-x-auto max-w-full custom-scrollbar">
        <table class="block xl:table w-full border-collapse text-left text-sm text-slate-700 dark:text-slate-200 max-w-full">
            <thead class="hidden xl:table-header-group">
                <tr class="bg-slate-100/70 dark:bg-slate-800/80 border-b border-slate-200/80 dark:border-slate-700/80 text-slate-500 dark:text-slate-400 font-bold text-[11px] uppercase tracking-wider">
                    <th class="px-3 py-3 w-10 text-center">
                        <input type="checkbox" id="cb-select-all" onclick="cbToggleAll(this)" class="rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sky-600 focus:ring-sky-500 cursor-pointer">
                    </th>
                    <th class="px-3 xl:px-4 py-3 xl:min-w-[130px]">Pelanggan</th>
                    <th class="px-3 xl:px-4 py-3 xl:min-w-[100px]">No. Tagihan</th>
                    <th class="px-3 xl:px-4 py-3 xl:min-w-[95px]">Jatuh Tempo</th>
                    <th class="px-3 xl:px-4 py-3 text-right xl:min-w-[95px]">Sisa Tagihan</th>
                    <th class="px-3 xl:px-4 py-3 xl:min-w-[110px]">Nominal Bayar</th>
                    <th class="px-3 xl:px-4 py-3 xl:min-w-[85px]">Metode</th>
                    <th class="px-3 xl:px-4 py-3 xl:min-w-[105px]">Tgl Ditagih</th>
                    <th class="px-3 xl:px-4 py-3 text-right xl:min-w-[150px] xl:whitespace-nowrap">Aksi</th>
                </tr>
            </thead>
            <tbody class="block xl:table-row-group p-2.5 sm:p-3 xl:p-0 space-y-2.5 xl:space-y-0 divide-y-0 xl:divide-y divide-slate-100 dark:divide-slate-700/50">
                @forelse ($invoices as $invoice)
                    <tr class="block xl:table-row bg-white dark:bg-slate-800/90 xl:bg-transparent rounded-2xl xl:rounded-none border border-slate-200/80 dark:border-slate-700/80 xl:border-x-0 xl:border-t-0 xl:border-b p-3 sm:p-3.5 xl:p-0 shadow-xs xl:shadow-none hover:border-sky-300 dark:hover:border-sky-600/50 xl:hover:bg-slate-50/80 dark:xl:hover:bg-slate-700/30 transition-all space-y-2 xl:space-y-0" data-invoice-row="{{ $invoice->id }}">
                        {{-- 1. Checkbox & Mobile Card Header --}}
                        <td class="block xl:table-cell px-0 pb-1 xl:px-3 xl:py-3 xl:w-10">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <input type="checkbox" class="cb-row-checkbox rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-900 text-sky-600 focus:ring-sky-500 cursor-pointer w-4 h-4 shrink-0" value="{{ $invoice->id }}">
                                    <div class="xl:hidden font-bold text-sm text-slate-900 dark:text-slate-100 truncate">
                                        {{ $invoice->customer->full_name ?? '-' }}
                                    </div>
                                </div>
                                <div class="xl:hidden shrink-0">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-mono font-semibold bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        {{ $invoice->customer->cid ?? $invoice->customer->customer_code ?? '-' }}
                                    </span>
                                </div>
                            </div>
                        </td>

                        {{-- 2. Customer Info (Desktop Table View) --}}
                        <td class="hidden xl:table-cell px-3 xl:px-4 py-3 xl:min-w-[130px]">
                            <div class="font-bold text-slate-900 dark:text-slate-100 leading-snug truncate max-w-[180px] xl:max-w-none">{{ $invoice->customer->full_name ?? '-' }}</div>
                            <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">{{ $invoice->customer->cid ?? $invoice->customer->customer_code ?? '-' }}</div>
                        </td>

                        {{-- Mobile / Tablet / Laptop-1024 Card Details Grid --}}
                        <td class="block xl:hidden px-0 py-0.5" colspan="1">
                            <div class="bg-slate-50 dark:bg-slate-800/60 p-2.5 rounded-xl border border-slate-100 dark:border-slate-700/50 space-y-1.5">
                                {{-- Row 1: No. Invoice --}}
                                <div class="flex items-center justify-between text-xs gap-2">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 tracking-wider">No. Invoice</span>
                                    <span class="font-mono font-semibold text-slate-800 dark:text-slate-200 text-xs truncate">{{ $invoice->invoice_number }}</span>
                                </div>

                                {{-- Row 2: Tanggal Jatuh Tempo --}}
                                <div class="flex items-center justify-between text-xs gap-2 border-t border-slate-200/60 dark:border-slate-700/40 pt-1.5">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 tracking-wider">Jatuh Tempo</span>
                                    <div class="shrink-0 text-right">
                                        @if ($invoice->due_date && $invoice->due_date->isPast())
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[10px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                {{ $invoice->due_date->format('d/m/Y') }} (Terlewat)
                                            </span>
                                        @else
                                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 font-mono">
                                                {{ $invoice->due_date?->format('d/m/Y') ?? '-' }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Row 3: Sisa Tagihan --}}
                                <div class="flex items-center justify-between border-t border-slate-200/60 dark:border-slate-700/40 pt-1.5">
                                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Sisa Tagihan</span>
                                    <span class="cb-sisa font-mono font-bold text-amber-600 dark:text-amber-400 text-base">Rp {{ number_format((float) $invoice->remaining_amount, 0, ',', '.') }}</span>
                                </div>
                            </div>
                        </td>

                        {{-- 3. Invoice Number (Desktop Table View) --}}
                        <td class="hidden xl:table-cell px-3 xl:px-4 py-3 font-mono text-xs whitespace-nowrap text-slate-600 dark:text-slate-300 xl:min-w-[100px]">
                            {{ $invoice->invoice_number }}
                        </td>

                        {{-- 4. Due Date (Desktop Table View) --}}
                        <td class="hidden xl:table-cell px-3 xl:px-4 py-3 whitespace-nowrap text-xs xl:min-w-[95px]">
                            @if ($invoice->due_date && $invoice->due_date->isPast())
                                <span class="inline-flex items-center gap-1 px-1.5 xl:px-2 py-0.5 rounded-lg text-[10px] xl:text-[11px] font-bold bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                    {{ $invoice->due_date->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-slate-600 dark:text-slate-300 font-medium">
                                    {{ $invoice->due_date?->format('d/m/Y') ?? '-' }}
                                </span>
                            @endif
                        </td>

                        {{-- 5. Remaining Amount (Desktop Table View) --}}
                        <td class="hidden xl:table-cell px-3 xl:px-4 py-3 text-right font-mono font-bold text-amber-600 dark:text-amber-400 text-sm xl:text-base xl:min-w-[95px]">
                            <span class="cb-sisa">Rp {{ number_format((float) $invoice->remaining_amount, 0, ',', '.') }}</span>
                        </td>

                        {{-- 6. Payment Amount Input --}}
                        <td class="block xl:table-cell px-0 py-0.5 xl:px-3 xl:py-3 xl:min-w-[110px]">
                            <div class="space-y-0.5">
                                <label class="xl:hidden text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Nominal Bayar</label>
                                <div class="relative">
                                    <span class="absolute left-2 top-2 text-xs font-semibold text-slate-400">Rp</span>
                                    {{-- data-rupiah butuh type="text"; batas atas
                                         pindah ke data-max karena atribut `max`
                                         cuma ditegakkan browser pada input number.
                                         Pengecekannya ada di collector-pay-script. --}}
                                    <input type="text" inputmode="decimal" data-rupiah data-max="{{ (float) $invoice->remaining_amount }}" value="{{ \App\Helpers\FormatHelper::rupiahInput($invoice->remaining_amount) }}" class="cb-amount w-full xl:w-32 2xl:w-36 font-mono text-xs pl-6 pr-1.5 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                </div>
                            </div>
                        </td>

                        {{-- 7. Payment Method Select (Side-by-side float-left 48% on mobile/tablet) --}}
                        <td class="block float-left w-[48%] xl:w-auto xl:float-none xl:table-cell px-0 py-0.5 xl:px-3 xl:py-3 xl:min-w-[85px]">
                            <div class="space-y-0.5">
                                <label class="xl:hidden text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Metode</label>
                                <select class="cb-method w-full xl:w-26 2xl:w-28 text-xs px-1.5 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                    <option value="cash">Cash</option>
                                    <option value="transfer">Transfer</option>
                                    <option value="qris">QRIS</option>
                                    <option value="lainnya">Lainnya</option>
                                </select>
                            </div>
                        </td>

                        {{-- 8. Collected Date Input (Side-by-side float-right 48% on mobile/tablet) --}}
                        <td class="block float-right w-[48%] xl:w-auto xl:float-none xl:table-cell px-0 py-0.5 xl:px-3 xl:py-3 xl:min-w-[105px]">
                            <div class="space-y-0.5">
                                <label class="xl:hidden text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider block">Tgl Ditagih</label>
                                <input type="date" class="cb-collected-date w-full xl:w-30 2xl:w-32 text-xs px-1.5 py-1.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500" value="{{ now()->format('Y-m-d') }}">
                            </div>
                        </td>

                        {{-- 9. Single Pay & Visit Action Buttons --}}
                        <td class="block clear-both xl:clear-none xl:table-cell px-0 pt-2 xl:px-3 xl:py-3 xl:text-right xl:whitespace-nowrap xl:min-w-[150px]">
                            <div class="flex items-center justify-end gap-1 shrink-0 pt-2 border-t border-slate-100 dark:border-slate-700/60 xl:border-t-0 xl:pt-0">
                                @if ($canLogVisit ?? true)
                                    <button type="button" 
                                            @click="$dispatch('open-visit-modal', { id: {{ $invoice->customer_id }}, name: '{{ e($invoice->customer->full_name ?? '-') }}', cid: '{{ e($invoice->customer->cid ?? $invoice->customer->customer_code ?? '-') }}' })" 
                                            class="flex-1 xl:flex-none shrink-0 inline-flex items-center justify-center gap-1 px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700/80 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl transition-all text-xs font-semibold shadow-2xs active:scale-95 cursor-pointer"
                                            title="Catat Kunjungan">
                                        <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <span class="hidden 2xl:inline">Catat Kunjungan</span><span class="2xl:hidden">Kunjungan</span>
                                    </button>
                                @endif
                                <button type="button" onclick="cbSubmitSingle({{ $invoice->id }})" class="flex-1 xl:flex-none shrink-0 inline-flex items-center justify-center gap-1 px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl transition-all text-xs font-bold shadow-xs active:scale-95 cursor-pointer">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Bayar</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">{{ $emptyMessage }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-4 sm:px-6 py-4 border-t border-slate-200/80 dark:border-slate-700/80 bg-white dark:bg-slate-800/40">
        {{ $invoices->links() }}
    </div>
</div>

{{-- Sticky Floating Batch Payment Action Bar --}}
@if ($invoices->isNotEmpty())
    <div id="cb-floating-bar" class="mt-6 sticky bottom-4 z-30 hidden transition-all duration-300">
        <div class="bg-slate-900/95 dark:bg-slate-900/95 backdrop-blur-md text-white rounded-2xl shadow-xl p-3.5 sm:px-5 sm:py-3.5 border border-slate-800 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2 justify-center sm:justify-start">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span id="cb-count" class="font-semibold text-slate-200">0 baris dipilih</span>
            </div>
            <button type="button" id="cb-submit" onclick="cbSubmitBatch()" class="w-full sm:w-auto px-5 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 rounded-xl font-bold transition-all disabled:opacity-40 disabled:cursor-not-allowed shadow-md cursor-pointer text-center" disabled>
                Bayar Massal (Baris Terpilih)
            </button>
        </div>
    </div>
@endif
