@props([
    // `roomy` = dialog/drawer (target sentuh besar); `compact` = sidebar desktop.
    'size' => 'roomy',
])

@php
    $roomy = $size === 'roomy';
@endphp

{{--
    Daftar "Barang Terpilih" — dipakai di dialog/drawer pemilih stok dan
    sidebar desktop. Tiap SN/roll bisa dilepas satu-satu, satu barang bisa
    dihapus sekaligus. Ini umpan balik utama "barang sudah dipilih": dulu
    pilihan cuma masuk ke textarea di form sehingga hampir tak terlihat.
    Bergantung pada pickedRows()/unpickCode()/releaseRow() dari
    warehouseStockPickerMixin.
--}}
<div {{ $attributes->class(['space-y-2']) }}>
    <template x-if="pickedRows().length === 0">
        <div @class(['text-center rounded-lg border border-dashed border-slate-200 dark:border-slate-700', 'py-10 px-4' => $roomy, 'py-5 px-3' => ! $roomy])>
            <p @class(['font-semibold text-slate-700 dark:text-slate-200', 'text-sm' => $roomy, 'text-xs' => ! $roomy])>Belum ada barang dipilih</p>
            <p class="mt-1 text-[11px] text-slate-400">{{ $slot->isEmpty() ? 'Pilih chip SN / lot / roll dari stok.' : $slot }}</p>
        </div>
    </template>

    <template x-for="(picked, i) in pickedRows()" :key="i">
        <div @class(['rounded-lg border border-sky-200 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-950/20', 'p-3' => $roomy, 'p-2.5' => ! $roomy])>
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <p @class(['font-semibold text-slate-800 dark:text-slate-100 leading-snug', 'text-sm' => $roomy, 'text-xs' => ! $roomy]) x-text="picked.name"></p>
                    <p class="text-[10px] font-mono text-slate-400">
                        <span x-text="picked.code"></span>
                        <span x-show="picked.codes.length > 0" x-text="' · ' + picked.codes.length + (picked.field === 'roll_codes' ? ' roll' : ' SN')"></span>
                    </p>
                </div>
                <button type="button" @click="releaseRow(picked.row)"
                        @class(['shrink-0 inline-flex items-center gap-1 px-2 rounded-lg text-[11px] font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 cursor-pointer', 'min-h-[32px]' => $roomy, 'min-h-[26px]' => ! $roomy])
                        :aria-label="'Hapus ' + picked.name">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                    Hapus
                </button>
            </div>

            {{-- SN / Roll: tiap kode bisa dilepas satu-satu --}}
            <div class="mt-2 flex flex-wrap gap-1.5" x-show="picked.codes.length > 0">
                <template x-for="code in picked.codes" :key="code">
                    <button type="button" @click="unpickCode(picked.row.item_id, picked.field, code)"
                            :class="picked.field === 'roll_codes' ? 'bg-amber-600 border-amber-600 hover:bg-amber-700' : 'bg-sky-600 border-sky-600 hover:bg-sky-700'"
                            @class(['inline-flex items-center gap-1.5 rounded-lg border text-white font-mono font-bold cursor-pointer active:scale-95', 'min-h-[34px] pl-2.5 pr-2 py-1 text-[11px]' => $roomy, 'pl-2 pr-1.5 py-0.5 text-[10px]' => ! $roomy])
                            :aria-label="'Lepas ' + code" title="Klik untuk melepas">
                        <span x-text="code"></span>
                        <svg class="w-3.5 h-3.5 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </template>
            </div>

            {{-- Reguler / lot --}}
            <template x-if="picked.codes.length === 0">
                <p class="mt-1.5 text-xs text-slate-600 dark:text-slate-300">
                    <span x-show="picked.row.lot_no">Lot <span class="font-mono font-semibold" x-text="picked.row.lot_no"></span> &bull; </span>
                    <span class="font-mono font-bold" x-text="picked.row.qty || 0"></span> <span x-text="picked.row.unit"></span>
                    <span x-show="!(parseFloat(picked.row.qty) > 0)" class="text-amber-600 dark:text-amber-400"> &mdash; isi jumlah di form</span>
                </p>
            </template>
        </div>
    </template>
</div>
