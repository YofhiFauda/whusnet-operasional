@extends('layouts.app')

@section('title', 'Buat Jual Beli SN')
@section('page_title', 'Buat Jual Beli SN')

@section('content')
<x-warehouse.header active="branch-sales" title="Buat Jual Beli SN" subtitle="Pilih Cabang penjual, lalu beri harga jual pada tiap unit ber-SN yang dikirim." />

<div class="mx-auto max-w-5xl space-y-5 pb-24" x-data="branchSaleCreate()">
    <div class="flex items-center justify-between">
        <a href="{{ route('warehouse.branch-sales.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-sky-700 hover:underline dark:text-sky-400">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            <span>Daftar jual beli</span>
        </a>
    </div>

    @if(session('error'))
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200 flex items-start gap-3">
            <svg class="h-5 w-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    @if($errors->any())
        <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-200 flex items-start gap-3">
            <svg class="h-5 w-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div>{{ $errors->first() }}</div>
        </div>
    @endif

    {{-- KARTU RUTE JUAL BELI (Cabang Penjual & Pembeli) --}}
    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-xs dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-5 md:grid-cols-2 items-start">
            {{-- Sisi Kiri: Cabang Penjual & Pencarian SN --}}
            <form method="GET" action="{{ route('warehouse.branch-sales.create') }}" id="source-pop-form" class="space-y-2">
                <label for="from_pop_id" class="block text-sm font-semibold text-slate-800 dark:text-slate-200">
                    Cabang penjual (Asal)
                </label>
                <div class="flex gap-2">
                    <select id="from_pop_id" name="from_pop_id" onchange="this.form.submit()" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white p-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        <option value="">Pilih Cabang</option>
                        @foreach($sourcePops as $pop)
                            <option value="{{ $pop->id }}" @selected($source?->id === $pop->id)>{{ $pop->name }}</option>
                        @endforeach
                    </select>
                    @if($source)
                        <div class="relative">
                            <input type="search" name="q" value="{{ $search }}" placeholder="Cari nomor SN..." aria-label="Cari nomor SN" class="w-36 sm:w-44 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                        </div>
                        <button type="submit" class="rounded-lg bg-slate-800 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600 transition-colors">
                            Tampilkan SN
                        </button>
                    @endif
                </div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih Cabang yang akan mengirimkan dan menjual unit ber-SN.</p>
            </form>

            {{-- Sisi Kanan: Cabang Pembeli & Aksi Cepat Atas --}}
            @if($source)
                <div class="space-y-2 border-t md:border-t-0 md:border-l md:pl-5 border-slate-200 dark:border-slate-800 pt-4 md:pt-0">
                    <label for="to_pop_id" class="block text-sm font-semibold text-slate-800 dark:text-slate-200">
                        Cabang pembeli <span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <div class="flex gap-2">
                        <select id="to_pop_id" name="to_pop_id" required form="branch-sales-form" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white p-2.5 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                            <option value="">Pilih Cabang tujuan</option>
                            @foreach($destinationPops as $pop)
                                @if($pop->id !== $source->id)
                                    <option value="{{ $pop->id }}" @selected(old('to_pop_id') == $pop->id)>{{ $pop->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <button type="submit"
                                form="branch-sales-form"
                                :disabled="selectedCount === 0 || !hasDestination"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-sky-800 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer">
                            <span>Kirim dan buat dokumen</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500 dark:text-slate-400">Cabang tujuan yang menerima dan membeli stok.</span>
                        <span x-show="selectedCount > 0" class="font-semibold text-sky-700 dark:text-sky-400" x-text="`${selectedCount} SN terpilih (${formatTotalRupiah()})`"></span>
                    </div>
                </div>
            @else
                <div class="flex items-center justify-center p-6 border border-dashed border-slate-200 dark:border-slate-800 rounded-lg text-sm text-slate-400">
                    Pilih cabang penjual untuk mulai menampilkan daftar SN.
                </div>
            @endif
        </div>
    </div>

    @if($source)
    <form method="POST" action="{{ route('warehouse.branch-sales.store') }}" id="branch-sales-form" class="space-y-4">
        @csrf
        <input type="hidden" name="from_pop_id" value="{{ $source->id }}">

        {{-- TOOLBAR CEPAT (Filter lokal & Set Harga Sekaligus) --}}
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-slate-50 dark:bg-slate-800/60 p-3 rounded-xl border border-slate-200 dark:border-slate-800 text-xs">
            <div class="flex items-center gap-2 flex-1 max-w-sm">
                <div class="relative w-full">
                    <input type="text"
                           x-model="clientFilter"
                           placeholder="Filter cepat di halaman ini..."
                           class="w-full pl-8 pr-3 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs focus:ring-1 focus:ring-sky-500 dark:text-slate-100">
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-slate-500 dark:text-slate-400">Set harga seragam untuk pilihan:</span>
                <div class="flex items-center gap-1.5">
                    <span class="text-slate-400">Rp</span>
                    <input type="text"
                           x-model="bulkPrice"
                           @keydown.enter.prevent="applyBulkPrice()"
                           placeholder="230.000"
                           class="w-28 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-2 py-1 text-xs font-mono dark:text-slate-100">
                    <button type="button"
                            @click="applyBulkPrice()"
                            class="rounded-lg bg-sky-100 hover:bg-sky-200 text-sky-800 dark:bg-sky-950 dark:hover:bg-sky-900 dark:text-sky-200 px-2.5 py-1 font-semibold transition-colors cursor-pointer">
                        Terapkan
                    </button>
                </div>
            </div>
        </div>

        {{-- TABEL SN DENGAN STICKY HEADER --}}
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-slate-100 text-left text-xs uppercase tracking-wider text-slate-600 dark:bg-slate-800 dark:text-slate-300 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="p-3 w-12 text-center">
                            <input type="checkbox"
                                   @change="toggleAll($el.checked)"
                                   :checked="isAllChecked"
                                   :indeterminate.prop="isIndeterminate"
                                   aria-label="Pilih semua SN di halaman ini"
                                   class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800 cursor-pointer">
                        </th>
                        <th class="p-3 font-semibold">Barang</th>
                        <th class="p-3 font-semibold">Nomor SN</th>
                        <th class="p-3 font-semibold">Kondisi</th>
                        <th class="p-3 font-semibold text-right sm:text-left">Harga jual / SN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($serials as $serial)
                    <tr x-show="matchesFilter('{{ $serial->serial_number }}', '{{ addslashes($serial->item->name) }}')"
                        :class="selectedMap['{{ $serial->id }}'] ? 'bg-sky-50/70 dark:bg-sky-950/20' : 'hover:bg-slate-50/50 dark:hover:bg-slate-800/30'"
                        class="transition-colors">
                        <td class="p-3 text-center">
                            <input type="checkbox"
                                   name="lines[{{ $serial->id }}][serial_id]"
                                   value="{{ $serial->id }}"
                                   data-serial-id="{{ $serial->id }}"
                                   @checked(old('lines.'.$serial->id.'.serial_id'))
                                   @change="toggleRow('{{ $serial->id }}', $el.checked)"
                                   aria-label="Pilih {{ $serial->serial_number }}"
                                   class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 dark:border-slate-600 dark:bg-slate-800 cursor-pointer">
                        </td>
                        <td class="p-3 font-medium text-slate-800 dark:text-slate-200">
                            {{ $serial->item->name }}
                        </td>
                        <td class="p-3 font-mono text-slate-900 dark:text-slate-100 select-all">
                            {{ $serial->serial_number }}
                        </td>
                        <td class="p-3">
                            <x-warehouse.condition-badge :condition="$serial->condition ?? \App\Enums\ItemCondition::NEW" :checked="$serial->condition_checked_at !== null" />
                        </td>
                        <td class="p-3">
                            <div class="relative w-44">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-xs text-slate-400">Rp</span>
                                <input type="text"
                                       inputmode="decimal"
                                       data-rupiah
                                       name="lines[{{ $serial->id }}][sale_price]"
                                       data-price-id="{{ $serial->id }}"
                                       value="{{ old('lines.'.$serial->id.'.sale_price') }}"
                                       @input="onPriceInput('{{ $serial->id }}', $el.value)"
                                       placeholder="230.000"
                                       aria-label="Harga jual {{ $serial->serial_number }}"
                                       class="w-full rounded-lg border border-slate-300 bg-white pl-8 pr-3 py-1.5 text-sm font-mono dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 focus:border-sky-500 focus:ring-1 focus:ring-sky-500">
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-500 dark:text-slate-400">
                            Tidak ada SN tersedia di Cabang ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
            <p>100 SN layak jual (baru atau bekas yang sudah diperiksa) per halaman; gunakan pencarian atau halaman berikutnya untuk SN lain. Harga hanya wajib untuk SN yang dicentang. Pilihan dan harga yang sudah diisi <strong>tidak terbawa</strong> kalau pindah halaman — kirim dokumen per halaman kalau SN yang dipilih tersebar di beberapa halaman.</p>
        </div>

        @if($serials->hasPages())
            <div class="pt-2">
                {{ $serials->links() }}
            </div>
        @endif

        {{-- STICKY FLOATING ACTION BAR (Memecahkan masalah harus scroll paling bawah!) --}}
        <div class="sticky bottom-4 z-30 transition-all duration-300">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/95 dark:bg-slate-900/95 p-3.5 sm:px-6 sm:py-3.5 text-white shadow-2xl backdrop-blur-md flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-3 w-3 relative shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                    </span>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <span class="font-bold text-sky-400" x-text="`${selectedCount} SN dipilih`">0 SN dipilih</span>
                        <span class="text-slate-400">•</span>
                        <span class="text-slate-300">Total: <strong class="font-mono text-white text-base" x-text="formatTotalRupiah()">Rp 0</strong></span>
                        <template x-if="missingPriceCount > 0">
                            <span class="rounded bg-amber-500/20 px-2 py-0.5 text-xs text-amber-300 font-medium">
                                ⚠️ <span x-text="missingPriceCount"></span> SN belum ada harga
                            </span>
                        </template>
                        <template x-if="!hasDestination">
                            <span class="rounded bg-rose-500/20 px-2 py-0.5 text-xs text-rose-300 font-medium">
                                ⚠️ Pilih cabang pembeli
                            </span>
                        </template>
                    </div>
                </div>

                <div class="flex items-center gap-2 justify-end">
                    <button type="submit"
                            :disabled="selectedCount === 0 || !hasDestination"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-sky-600 px-6 py-2.5 text-sm font-bold text-white shadow-md hover:bg-sky-500 active:bg-sky-700 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer">
                        <span>Kirim dan buat dokumen</span>
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </form>
    @endif
</div>

<script>
function branchSaleCreate() {
    return {
        selectedMap: {},
        priceMap: {},
        bulkPrice: '',
        clientFilter: '',
        hasDestination: false,
        totalSerialsOnPage: 0,

        init() {
            const destSelect = document.getElementById('to_pop_id');
            if (destSelect) {
                this.hasDestination = !!destSelect.value;
                destSelect.addEventListener('change', () => {
                    this.hasDestination = !!destSelect.value;
                });
            }

            const checkboxes = document.querySelectorAll('input[type="checkbox"][data-serial-id]');
            this.totalSerialsOnPage = checkboxes.length;
            checkboxes.forEach(cb => {
                const id = cb.dataset.serialId;
                this.selectedMap[id] = cb.checked;
                const priceInput = document.querySelector(`input[data-price-id="${id}"]`);
                if (priceInput) {
                    this.priceMap[id] = priceInput.value;
                }
            });
        },

        get selectedCount() {
            return Object.values(this.selectedMap).filter(Boolean).length;
        },

        get totalAmount() {
            let total = 0;
            for (const [id, selected] of Object.entries(this.selectedMap)) {
                if (selected) {
                    const priceVal = this.priceMap[id];
                    if (priceVal) {
                        const num = window.Rupiah ? window.Rupiah.angka(priceVal) : parseFloat(String(priceVal).replace(/[^\d]/g, ''));
                        if (!isNaN(num) && num > 0) {
                            total += num;
                        }
                    }
                }
            }
            return total;
        },

        get missingPriceCount() {
            let count = 0;
            for (const [id, selected] of Object.entries(this.selectedMap)) {
                if (selected) {
                    const priceVal = this.priceMap[id];
                    const num = window.Rupiah ? window.Rupiah.angka(priceVal) : parseFloat(String(priceVal || '').replace(/[^\d]/g, ''));
                    if (isNaN(num) || num <= 0) {
                        count++;
                    }
                }
            }
            return count;
        },

        formatTotalRupiah() {
            if (window.Rupiah) {
                return 'Rp ' + window.Rupiah.format(Math.round(this.totalAmount).toString());
            }
            return 'Rp ' + new Intl.NumberFormat('id-ID').format(this.totalAmount);
        },

        get isAllChecked() {
            return this.totalSerialsOnPage > 0 && this.selectedCount === this.totalSerialsOnPage;
        },

        get isIndeterminate() {
            return this.selectedCount > 0 && this.selectedCount < this.totalSerialsOnPage;
        },

        toggleAll(checked) {
            const checkboxes = document.querySelectorAll('input[type="checkbox"][data-serial-id]');
            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (!row || row.style.display !== 'none') {
                    cb.checked = checked;
                    this.selectedMap[cb.dataset.serialId] = checked;
                }
            });
        },

        toggleRow(id, checked) {
            this.selectedMap[id] = checked;
            if (checked) {
                const priceInput = document.querySelector(`input[data-price-id="${id}"]`);
                if (priceInput && (!priceInput.value || priceInput.value.trim() === '')) {
                    if (this.bulkPrice) {
                        priceInput.value = window.Rupiah ? window.Rupiah.format(this.bulkPrice) : this.bulkPrice;
                        this.priceMap[id] = priceInput.value;
                    } else {
                        priceInput.focus();
                    }
                }
            }
        },

        onPriceInput(id, val) {
            this.priceMap[id] = val;
            const num = window.Rupiah ? window.Rupiah.angka(val) : parseFloat(String(val).replace(/[^\d]/g, ''));
            if (!isNaN(num) && num > 0 && !this.selectedMap[id]) {
                const cb = document.querySelector(`input[type="checkbox"][data-serial-id="${id}"]`);
                if (cb) {
                    cb.checked = true;
                    this.selectedMap[id] = true;
                }
            }
        },

        applyBulkPrice() {
            if (!this.bulkPrice || this.bulkPrice.trim() === '') return;
            const formatted = window.Rupiah ? window.Rupiah.format(this.bulkPrice) : this.bulkPrice;
            this.bulkPrice = formatted;
            for (const [id, selected] of Object.entries(this.selectedMap)) {
                if (selected) {
                    const priceInput = document.querySelector(`input[data-price-id="${id}"]`);
                    if (priceInput) {
                        priceInput.value = formatted;
                        this.priceMap[id] = formatted;
                    }
                }
            }
        },

        matchesFilter(serialNumber, itemName) {
            if (!this.clientFilter || this.clientFilter.trim() === '') return true;
            const q = this.clientFilter.toLowerCase().trim();
            return (serialNumber && serialNumber.toLowerCase().includes(q)) ||
                   (itemName && itemName.toLowerCase().includes(q));
        }
    };
}
</script>
@endsection
