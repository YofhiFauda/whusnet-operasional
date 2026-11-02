@extends('layouts.app')

@section('title', 'Cek & Cocokkan Stok - Whusnet Operasional')
@section('page_title', 'Cek & Cocokkan Stok')

@section('content')

{{--
    Kartu 1 Koreksi Stok — menggantikan "Stock Opname" (qty) + "Opname SN /
    Roll". User mengisi HASIL HITUNG, sistem yang menghitung selisih. Simpan
    tetap ke endpoint lama: qty → opname.store, SN/roll → unit-opname.store.
    Kedua URL dirender server (data-url-*); Alpine cuma memilih salah satu.
--}}
<x-warehouse.header active="stock" title="Cek & Cocokkan Stok" subtitle="Isi hasil hitung fisik di gudang — sistem menghitung selisihnya. Hasil yang sama persis tetap disimpan sebagai bukti sudah dicek." backUrl="{{ route('warehouse.adjustments.index') }}" />

<div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-700 dark:bg-slate-800/90 sm:p-8">
    {{-- session('error') sudah tampil lewat toast global (x-toast di layout) —
         kotak di sini dulu bikin pesan yang sama muncul dua kali di layar. --}}
    <form method="POST" enctype="multipart/form-data" class="space-y-4"
          action="{{ route('warehouse.adjustments.opname.store') }}"
          data-url-qty="{{ route('warehouse.adjustments.opname.store') }}"
          data-url-unit="{{ route('warehouse.adjustments.unit-opname.store') }}"
          data-resolve-url="{{ $resolveUrl }}"
          data-prefill-pop="{{ old('pop_id', $prefill['pop_id']) }}"
          data-prefill-q="{{ old('q', $prefill['q']) }}"
          data-prefill-lot="{{ old('lot_no', $prefill['lot_no']) }}"
          x-data="stockCorrectionForm($el.dataset)"
          :action="result.type === 'serial' || result.type === 'roll' ? $root.dataset.urlUnit : $root.dataset.urlQty">
        @csrf

        @include('warehouse.adjustments.partials._unit-finder')

        {{-- Barang curah: jumlah fisik, selisih dihitung live sebagai info saja. --}}
        <template x-if="result.type === 'qty'">
            <div class="space-y-4">
                <input type="hidden" name="item_id" :value="result.item_id">
                <template x-if="result.lots.length > 1">
                    <div>
                        <label for="chk-lot" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Tingkat harga</label>
                        <select id="chk-lot" name="lot_no" x-model="lotNo" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                            <template x-for="(lot, index) in result.lots" :key="lot.lot_no">
                                <option :value="lot.lot_no" x-text="(index === 0 ? 'Harga lama' : index === 1 ? 'Harga baru' : 'Lot ke-' + (index + 1)) + ' — stok ' + lot.qty"></option>
                            </template>
                        </select>
                    </div>
                </template>
                <template x-if="result.lots.length <= 1">
                    <input type="hidden" name="lot_no" :value="lotNo">
                </template>
                <div x-data="{ counted: @js((string) old('counted_qty', '')) }">
                    <label for="chk-counted" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Jumlah di rak sekarang <span class="text-rose-500">*</span></label>
                    <input id="chk-counted" type="number" step="0.01" min="0" name="counted_qty" required x-model="counted" placeholder="mis. 95"
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 font-mono text-xs font-bold dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    <p x-show="counted !== ''" x-cloak class="mt-1 text-xs"
                       :class="Number(counted) === currentQty() ? 'text-emerald-600' : 'text-amber-700 dark:text-amber-400'"
                       x-text="Number(counted) === currentQty() ? 'Cocok dengan sistem.' : 'Selisih ' + (Number(counted) - currentQty() > 0 ? '+' : '') + (Number(counted) - currentQty()) + ' ' + (result.unit || '')"></p>
                    @error('counted_qty') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                @include('warehouse.adjustments.partials._reason-evidence', ['idPrefix' => 'chk-qty', 'withReason' => false, 'notesPlaceholder' => 'mis. opname rutin akhir bulan'])
            </div>
        </template>

        {{-- SN: cukup ada / tidak ada. Bukti foto wajib (unit-opname.store). --}}
        <template x-if="result.type === 'serial'">
            <div class="space-y-4">
                <input type="hidden" name="tracking_type" value="serialized">
                <input type="hidden" name="code" :value="result.serial_number">
                <div>
                    <label for="chk-present" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Hasil cek <span class="text-rose-500">*</span></label>
                    <select id="chk-present" name="is_present" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                        <option value="1" @selected(old('is_present', '1') === '1')>Ada di rak, sesuai sistem</option>
                        <option value="0" @selected(old('is_present') === '0')>Tidak ditemukan — catat hilang</option>
                    </select>
                </div>
                @include('warehouse.adjustments.partials._reason-evidence', ['idPrefix' => 'chk-sn', 'reasonLabel' => 'Catatan pemeriksaan', 'reasonMultiline' => true, 'reasonMax' => 500, 'withNotes' => false, 'evidence' => 'required', 'evidenceLabel' => 'Foto pemeriksaan'])
            </div>
        </template>

        {{-- Roll: sisa meter fisik. 0 = roll tidak ditemukan. --}}
        <template x-if="result.type === 'roll'">
            <div class="space-y-4">
                <input type="hidden" name="tracking_type" value="roll">
                <input type="hidden" name="code" :value="result.roll_code">
                <div>
                    <label for="chk-length" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Sisa roll diukur (meter) <span class="text-rose-500">*</span></label>
                    <input id="chk-length" type="number" step="0.01" min="0" name="counted_length" required value="{{ old('counted_length') }}"
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Isi 0 bila roll tidak ditemukan.</p>
                    @error('counted_length') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                @include('warehouse.adjustments.partials._reason-evidence', ['idPrefix' => 'chk-roll', 'reasonLabel' => 'Catatan pemeriksaan', 'reasonMultiline' => true, 'reasonMax' => 500, 'withNotes' => false, 'evidence' => 'required', 'evidenceLabel' => 'Foto pemeriksaan'])
            </div>
        </template>

        <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-700">
            <a href="{{ route('warehouse.adjustments.index') }}" class="text-xs font-semibold text-slate-500 hover:underline dark:text-slate-400">Kembali</a>
            <button type="submit" :disabled="loading || !['qty', 'serial', 'roll'].includes(result.type)"
                    class="rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">Simpan Hasil Cek</button>
        </div>
    </form>
</div>

@endsection
