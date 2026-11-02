@extends('layouts.app')

@section('title', 'Barang Bermasalah - Whusnet Operasional')
@section('page_title', 'Barang Bermasalah')

@section('content')

@php
    /*
     * Kartu 2 Koreksi Stok — menggantikan "Penyesuaian Stok" (delta manual)
     * + "Tandai Status Perangkat". User memilih KEJADIAN; view ini yang
     * memetakan ke field endpoint lama:
     *   SN/roll → serial.store / roll.store (URL per-unit = adjust_url dari resolve)
     *             dengan new_status
     *   curah   → balance.store dengan qty_delta = tanda × jumlah
     * `unit` null = kejadian tidak berlaku untuk SN/roll; `sign` null = tidak
     * berlaku untuk curah; sign 0 = arah dipilih user (salah catat bisa +/−).
     * Penegak tetap controller + service (delta nol, foto wajib, scope).
     */
    $events = [
        ['key' => 'hilang', 'label' => 'Hilang', 'unit' => 'lost', 'sign' => -1, 'photo' => true],
        ['key' => 'rusak', 'label' => 'Rusak', 'unit' => 'damaged', 'sign' => -1, 'photo' => true],
        ['key' => 'dibuang', 'label' => 'Dibuang / dimusnahkan', 'unit' => 'scrapped', 'sign' => -1, 'photo' => true],
        ['key' => 'karantina', 'label' => 'Karantina (perlu dicek dulu)', 'unit' => 'quarantine', 'sign' => null, 'photo' => false],
        ['key' => 'salah_catat', 'label' => 'Salah catat', 'unit' => null, 'sign' => 0, 'photo' => false],
    ];
@endphp

<x-warehouse.header active="stock" title="Ada Barang Bermasalah" subtitle="Catat barang hilang, rusak, dibuang, perlu dikarantina, atau jumlah yang salah catat." backUrl="{{ route('warehouse.adjustments.index') }}" />

<div class="max-w-2xl rounded-lg border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-700 dark:bg-slate-800/90 sm:p-8">
    {{-- session('error') sudah tampil lewat toast global (x-toast di layout) —
         kotak di sini dulu bikin pesan yang sama muncul dua kali di layar. --}}
    <form method="POST" enctype="multipart/form-data" class="space-y-4"
          action="{{ route('warehouse.adjustments.balance.store') }}"
          data-url-qty="{{ route('warehouse.adjustments.balance.store') }}"
          data-resolve-url="{{ $resolveUrl }}"
          data-prefill-pop="{{ old('pop_id', $prefill['pop_id']) }}"
          data-prefill-q="{{ old('q', $prefill['q']) }}"
          data-prefill-lot="{{ old('lot_no', $prefill['lot_no']) }}"
          x-data="Object.assign(stockCorrectionForm($el.dataset), {
              events: @js($events),
              eventKey: @js((string) old('event', '')),
              amount: @js((string) old('amount', '')),
              direction: @js((string) old('direction', '-1')),
              detail: @js((string) old('detail', '')),
              isUnit() { return this.result.type === 'serial' || this.result.type === 'roll'; },
              available() { return this.events.filter((e) => this.isUnit() ? e.unit !== null : (this.result.type === 'qty' && e.sign !== null)); },
              event() { return this.available().find((e) => e.key === this.eventKey) || null; },
              qtyDelta() { const e = this.event(); if (!e) return ''; const sign = e.sign === 0 ? Number(this.direction) : e.sign; return String(sign * Math.abs(Number(this.amount || 0))); },
              photoRequired() { return this.isUnit() && !!this.event()?.photo; },
          })"
          :action="isUnit() ? result.adjust_url : $root.dataset.urlQty">
        @csrf

        @include('warehouse.adjustments.partials._unit-finder')

        <template x-if="['qty', 'serial', 'roll'].includes(result.type)">
            <div class="space-y-4">
                <div>
                    <p class="mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Apa yang terjadi? <span class="text-rose-500">*</span></p>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="e in available()" :key="e.key">
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-xs font-semibold"
                                   :class="eventKey === e.key ? 'border-amber-500 bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' : 'border-slate-200 text-slate-700 dark:border-slate-700 dark:text-slate-200'">
                                <input type="radio" name="event" class="sr-only" :value="e.key" x-model="eventKey" required>
                                <span x-text="e.label"></span>
                            </label>
                        </template>
                    </div>
                    {{-- reason = "kode_kejadian: keterangan" — kode baku buat
                         laporan, keterangan diketik tetap WAJIB seperti form
                         lama (audit, spec §6 risiko 4). --}}
                    <input type="hidden" name="reason" :value="eventKey + ': ' + detail.trim()">
                </div>

                <p x-show="isUnit() && !result.adjust_url" x-cloak class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                    Unit ini dikeluarkan dari gudang di luar akses Anda — koreksinya dilakukan oleh gudang asal.
                </p>

                <template x-if="isUnit()">
                    <input type="hidden" name="new_status" :value="event()?.unit || ''">
                </template>

                <template x-if="result.type === 'qty'">
                    <div class="space-y-4">
                        <input type="hidden" name="item_id" :value="result.item_id">
                        <template x-if="result.lots.length > 1">
                            <div>
                                <label for="prb-lot" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Tingkat harga</label>
                                <select id="prb-lot" name="lot_no" x-model="lotNo" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                                    <template x-for="(lot, index) in result.lots" :key="lot.lot_no">
                                        <option :value="lot.lot_no" x-text="(index === 0 ? 'Harga lama' : index === 1 ? 'Harga baru' : 'Lot ke-' + (index + 1)) + ' — stok ' + lot.qty"></option>
                                    </template>
                                </select>
                            </div>
                        </template>
                        <template x-if="result.lots.length <= 1">
                            <input type="hidden" name="lot_no" :value="lotNo">
                        </template>
                        <input type="hidden" name="qty_delta" :value="qtyDelta()">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="prb-amount" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Jumlah barang <span class="text-rose-500">*</span></label>
                                <input id="prb-amount" type="number" step="0.01" min="0.01" name="amount" required x-model="amount" placeholder="mis. 5"
                                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 font-mono text-xs font-bold dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                            </div>
                            <div x-show="event()?.sign === 0" x-cloak>
                                <label for="prb-direction" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Stok di sistem harus</label>
                                <select id="prb-direction" name="direction" x-model="direction" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                                    <option value="-1">Dikurangi</option>
                                    <option value="1">Ditambah</option>
                                </select>
                            </div>
                        </div>
                        <p x-show="event() && amount" x-cloak class="text-xs text-slate-500 dark:text-slate-400"
                           x-text="'Stok sistem jadi ' + (currentQty() + Number(qtyDelta())) + ' ' + (result.unit || '')"></p>
                    </div>
                </template>

                <div>
                    <label for="prb-detail" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Keterangan <span class="text-rose-500">*</span></label>
                    <textarea id="prb-detail" name="detail" rows="2" required maxlength="220" x-model="detail" placeholder="Ceritakan singkat: kenapa / di mana / kapan"
                              class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"></textarea>
                    @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>

                @include('warehouse.adjustments.partials._reason-evidence', [
                    'idPrefix' => 'prb',
                    'withReason' => false,
                    'withNotes' => false,
                    'evidence' => 'when',
                    'evidenceWhen' => 'photoRequired()',
                    'evidenceLabel' => 'Foto kondisi / BAP kehilangan',
                    'evidenceHint' => 'Wajib untuk perangkat (SN/roll) yang hilang, rusak, atau dibuang. Karantina tidak perlu foto.',
                ])
            </div>
        </template>

        <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-700">
            <a href="{{ route('warehouse.adjustments.index') }}" class="text-xs font-semibold text-slate-500 hover:underline dark:text-slate-400">Kembali</a>
            <button type="submit" :disabled="loading || !event() || (isUnit() && !result.adjust_url)"
                    class="rounded-lg bg-amber-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50">Simpan</button>
        </div>
    </form>
</div>

@endsection
