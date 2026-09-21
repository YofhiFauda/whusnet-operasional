@extends('layouts.app')

@section('title', 'Terima Modem dari Pelanggan - Whusnet Operasional')
@section('page_title', 'Terima Modem dari Pelanggan')

@section('content')
@php
    $inputClass = 'w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all';
    $labelClass = 'block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200';
@endphp

<x-warehouse.header active="custody" title="Terima Modem dari Pelanggan" subtitle="Pelanggan yang sudah putus mengantar modem sendiri ke gudang, tanpa task pengambilan." backUrl="{{ route('warehouse.returns.index') }}" />

@if(session('error'))
<div class="mb-4 max-w-2xl p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">{{ session('error') }}</div>
@endif
@if($errors->any())
<div class="mb-4 max-w-2xl p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
</div>
@endif

<div class="max-w-2xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-6 sm:p-8 shadow-xs space-y-5">

    {{-- ── Langkah 1: cari pelanggan (hanya yang sudah putus, dalam scope POP) ── --}}
    @if(! $customer)
    <form method="GET" action="{{ route('warehouse.returns.from-customer.create') }}" class="space-y-2">
        <label class="{{ $labelClass }}">Cari Pelanggan yang Sudah Putus Langganan</label>
        <div class="flex gap-2">
            <input type="text" name="q" value="{{ $search }}" placeholder="Nama, kode/CID pelanggan, No. HP, atau SN modem" class="{{ $inputClass }}" autofocus>
            <button type="submit" class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-bold whitespace-nowrap">Cari</button>
        </div>
    </form>

    @if($search !== '')
        @if($matches->isEmpty())
        <p class="text-xs text-slate-500 dark:text-slate-400">Tidak ada pelanggan putus langganan yang cocok dengan "{{ $search }}" di POP Anda.</p>
        @else
        <div class="divide-y divide-slate-100 dark:divide-slate-700/60 border border-slate-200 dark:border-slate-700/60 rounded-lg">
            @foreach($matches as $match)
            <a href="{{ route('warehouse.returns.from-customer.create', ['customer' => $match->id]) }}" class="flex items-center justify-between px-4 py-3 text-xs hover:bg-slate-50 dark:hover:bg-slate-900/40">
                <span>
                    <span class="font-bold text-slate-800 dark:text-slate-100">{{ $match->full_name }}</span>
                    <span class="ml-2 font-mono text-slate-500 dark:text-slate-400">{{ $match->cid ?? $match->customer_code }}</span>
                </span>
                <span class="font-bold text-sky-600 dark:text-sky-400">Pilih →</span>
            </a>
            @endforeach
        </div>
        @endif
    @endif

    {{-- ── Langkah 2: form penerimaan ── --}}
    @else
    @php
        $rows = collect(old('serials', []))->map(fn ($r) => ['serial_number' => $r['serial_number'] ?? '', 'item_id' => $r['item_id'] ?? ''])->values();

        if ($rows->isEmpty()) {
            $rows = $installedSerials->map(fn ($s) => ['serial_number' => $s->serial_number, 'item_id' => $s->item_id])->values();
        }

        if ($rows->isEmpty() && ($legacyHint['serial'] ?? null)) {
            // Model dipilih otomatis hanya kalau merek di data lama jelas; selain itu
            // dibiarkan kosong supaya staf memilih sendiri dari fisik yang dipegang.
            $rows = collect([['serial_number' => $legacyHint['serial'], 'item_id' => $legacyHint['item']?->id ?? '']]);
        }

        if ($rows->isEmpty()) {
            $rows = collect([['serial_number' => '', 'item_id' => '']]);
        }
    @endphp

    <div class="p-3.5 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60 flex items-center justify-between text-xs">
        <span>
            <span class="font-bold text-slate-900 dark:text-slate-100">{{ $customer->full_name }}</span>
            <span class="ml-2 font-mono text-slate-500 dark:text-slate-400">{{ $customer->cid ?? $customer->customer_code }}</span>
        </span>
        <a href="{{ route('warehouse.returns.from-customer.create') }}" class="font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400">Ganti pelanggan</a>
    </div>

    @if($blockedReason)
    <div class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800">{{ $blockedReason }}</div>
    @else
    @if($installedSerials->isNotEmpty())
    <p class="text-[11px] font-semibold text-sky-600 dark:text-sky-400">Tercatat terpasang di pelanggan ini: {{ $installedSerials->map(fn ($s) => $s->serial_number.' ('.$s->item?->name.')')->join(', ') }}</p>
    @elseif(! empty($legacyHint['serial']))
    <p class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">
        Data lama pelanggan mencatat SN: {{ $legacyHint['serial'] }}@if($legacyHint['label']) (perangkat: "{{ $legacyHint['label'] }}")@endif
        — cocokkan dengan stiker di modem. Data lama bisa keliru, SN di fisik yang berlaku.
    </p>
    @endif

    <form action="{{ route('warehouse.returns.from-customer.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4"
          x-data="{ rows: @js($rows->all()), add() { this.rows.push({ serial_number: '', item_id: '' }) }, remove(i) { this.rows.splice(i, 1) } }">
        @csrf
        <input type="hidden" name="customer_id" value="{{ $customer->id }}">

        <div>
            <label class="{{ $labelClass }}">Diterima di Gudang <span class="text-rose-500">*</span></label>
            <select name="cabang_pop_id" required class="{{ $inputClass }}">
                <option value="">— Pilih Gudang —</option>
                @foreach($cabangPops as $pop)
                <option value="{{ $pop->id }}" @selected((string) old('cabang_pop_id') === (string) $pop->id)>{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="{{ $labelClass }}">Modem yang Diterima <span class="text-rose-500">*</span></label>
            <p class="text-[11px] mb-2 text-slate-500 dark:text-slate-400">SN sesuai stiker di perangkat. Pilih model hanya kalau SN belum pernah tercatat di sistem.</p>
            <div class="space-y-2">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-center">
                        <input type="text" :name="`serials[${i}][serial_number]`" x-model="row.serial_number" maxlength="100" placeholder="Nomor seri (SN)" class="{{ $inputClass }}">
                        <select :name="`serials[${i}][item_id]`" x-model="row.item_id" class="{{ $inputClass }}">
                            <option value="">— Model (isi kalau SN belum tercatat) —</option>
                            @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->name }}</option>
                            @endforeach
                        </select>
                        <button type="button" @click="remove(i)" x-show="rows.length > 1" class="text-xs font-semibold px-2 py-2 text-rose-600">Hapus</button>
                    </div>
                </template>
            </div>
            <button type="button" @click="add()" class="mt-2 text-xs font-semibold text-sky-600 dark:text-sky-400">+ Tambah SN</button>
        </div>

        <div>
            <label class="{{ $labelClass }}">Kondisi Fisik <span class="text-rose-500">*</span></label>
            <div class="flex items-center gap-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60">
                <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200 cursor-pointer">
                    <input type="radio" name="condition" value="used_good" @checked(old('condition', 'used_good') === 'used_good')> <span>Bekas — Kondisi Baik</span>
                </label>
                <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200 cursor-pointer">
                    <input type="radio" name="condition" value="used_damaged" @checked(old('condition') === 'used_damaged')> <span>Bekas — Rusak</span>
                </label>
            </div>
        </div>

        <div>
            <label class="{{ $labelClass }}">Foto Kondisi Alat <span class="text-rose-500">*</span></label>
            <input type="file" name="condition_photo" accept="image/*" capture="environment" required class="w-full text-xs">
        </div>

        <div>
            <p class="{{ $labelClass }}">Kelengkapan yang Ikut Diserahkan</p>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach($accessoryOptions as $key => $label)
                <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-200 cursor-pointer">
                    <input type="checkbox" name="accessories[]" value="{{ $key }}" @checked(in_array($key, old('accessories', []), true))> <span>{{ $label }}</span>
                </label>
                @endforeach
            </div>
        </div>

        <div>
            <label class="{{ $labelClass }}">Nilai Taksiran per Unit (Rp) — Opsional</label>
            <input type="text" inputmode="decimal" data-rupiah name="estimated_value" value="{{ old('estimated_value') }}" placeholder="mis. 150.000" class="{{ $inputClass }}">
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Kosong tetap bisa diterima, dihitung Rp 0 di Laporan Bulanan.</p>
        </div>

        <div>
            <label class="{{ $labelClass }}">Catatan (Opsional)</label>
            <textarea name="notes" rows="2" maxlength="500" class="{{ $inputClass }}" placeholder="Keterangan penyerahan...">{{ old('notes') }}</textarea>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <a href="{{ route('warehouse.returns.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">Batal</a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-bold shadow-xs shadow-sky-600/20 transition-all cursor-pointer">Terima ke Gudang</button>
        </div>
    </form>
    @endif
    @endif
</div>

@endsection
