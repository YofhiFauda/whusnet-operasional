{{--
    Tiga keadaan kosong papan (spec §7) — pesannya sengaja berbeda supaya user
    tahu harus berbuat apa: ubah filter, isi stok, atau minta akses.

    `no_access` TIDAK boleh menyebut nama atau jumlah gudang apa pun (spec §8,
    Review Focus 5): user tanpa scope tidak berhak tahu gudang mana yang ada.
--}}
@php
    [$title, $hint] = match ($emptyState) {
        'no_access' => ['Anda belum punya gudang yang boleh diakses', 'Hubungi admin untuk mengatur cakupan gudang (POP) pada role Anda.'],
        'no_match' => ['Tidak ada barang yang cocok dengan pencarian atau filter', 'Coba kata kunci lain, atau hapus sebagian filter.'],
        default => ['Belum ada stok tersedia di gudang dalam cakupan Anda', 'Stok muncul di sini setelah barang diterima atau ditransfer ke gudang.'],
    };
@endphp
<div class="p-10 sm:p-14 text-center" role="status">
    <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 dark:text-slate-500" aria-hidden="true">
        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
    </div>
    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $title }}</h4>
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">{{ $hint }}</p>
    @if($emptyState === 'no_match')
    <a href="{{ route('warehouse.stock.index', ['view' => 'papan', 'pop_id' => '']) }}" class="mt-4 inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-xs font-semibold rounded-xl text-slate-700 dark:text-slate-200 transition-colors">Hapus semua filter</a>
    @endif
</div>
