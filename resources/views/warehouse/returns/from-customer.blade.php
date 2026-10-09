@extends('layouts.app')

@section('title', 'Terima Modem dari Pelanggan - Whusnet Operasional')
@section('page_title', 'Terima Modem dari Pelanggan')

@section('content')
@php
    $inputClass = 'w-full text-xs font-semibold px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all placeholder:text-slate-400';
    $labelClass = 'block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200';
@endphp

<x-warehouse.header active="returns-from-customer" title="Terima Modem dari Pelanggan" subtitle="Penerimaan langsung di kantor gudang cabang untuk pelanggan putus langganan yang mengantar perangkat sendiri." />

<x-warehouse.returns-nav active="from-customer" />

@if(session('error'))
<div class="mb-5 max-w-3xl p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-300 flex items-center gap-3">
    <svg class="w-5 h-5 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <span>{{ session('error') }}</span>
</div>
@endif

@if($errors->any())
<div class="mb-5 max-w-3xl p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-300 space-y-1.5">
    <div class="font-bold mb-1 flex items-center gap-2">
        <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span>Mohon periksa kembali isian form berikut:</span>
    </div>
    @foreach($errors->all() as $error)
    <div class="flex items-center gap-2 pl-6">
        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
        <span>{{ $error }}</span>
    </div>
    @endforeach
</div>
@endif

<div class="max-w-3xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 sm:p-8 shadow-xs space-y-6">

    {{-- =========================================================================
         LANGKAH 1: Cari Pelanggan Putus Langganan (Scope POP)
         ========================================================================= --}}
    @if(! $customer)
    <div class="space-y-5">
        <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-700/60">
            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </span>
            <div>
                <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100">Langkah 1: Cari Data Pelanggan</h2>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Hanya menampilkan pelanggan yang berstatus putus langganan (non-aktif) di cabang Anda</p>
            </div>
        </div>

        <form method="GET" action="{{ route('warehouse.returns.from-customer.create') }}" class="space-y-3">
            <label class="{{ $labelClass }}">Pencarian Pelanggan</label>
            <div class="flex flex-col sm:flex-row gap-2">
                <div class="relative flex-1">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Ketik nama pelanggan, CID, nomor HP, atau nomor seri SN..." class="{{ $inputClass }} pl-9.5" autofocus>
                    <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-1.5 px-6 py-2.5 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white rounded-xl text-xs font-bold shadow-xs shadow-sky-600/25 transition-all cursor-pointer">
                    <span>Cari Pelanggan</span>
                </button>
            </div>
        </form>

        @if($search !== '')
            @if($matches->isEmpty())
            <div class="p-8 text-center rounded-2xl bg-slate-50 dark:bg-slate-900/40 border border-dashed border-slate-300 dark:border-slate-700 space-y-2">
                <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 flex items-center justify-center mx-auto text-sm">🔍</div>
                <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200">Pelanggan Tidak Ditemukan</h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                    Tidak ditemukan data pelanggan berstatus putus yang cocok dengan kata kunci "<span class="font-bold text-slate-700 dark:text-slate-300">{{ $search }}</span>" dalam cakupan POP Anda.
                </p>
            </div>
            @else
            <div class="space-y-2">
                <p class="text-xs font-bold text-slate-600 dark:text-slate-400">Hasil Pencarian ({{ $matches->count() }} pelanggan ditemukan):</p>
                <div class="divide-y divide-slate-100 dark:divide-slate-700/60 border border-slate-200 dark:border-slate-700/60 rounded-2xl overflow-hidden bg-slate-50/50 dark:bg-slate-900/30">
                    @foreach($matches as $match)
                    <a href="{{ route('warehouse.returns.from-customer.create', ['customer' => $match->id]) }}"
                       class="flex flex-col sm:flex-row sm:items-center justify-between p-4 gap-2 hover:bg-sky-50/60 dark:hover:bg-sky-950/40 transition-colors group">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-bold text-slate-900 dark:text-slate-100 text-xs sm:text-sm group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors">
                                    {{ $match->full_name }}
                                </span>
                                <span class="font-mono text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-200/80 dark:bg-slate-700 text-slate-700 dark:text-slate-300">
                                    {{ $match->cid ?? $match->customer_code }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 flex items-center gap-3">
                                <span>📞 {{ $match->phone_number ?? '-' }}</span>
                                <span class="truncate max-w-[250px]">📍 {{ $match->customerAddress?->full_address ?? $match->address ?? '-' }}</span>
                            </p>
                        </div>
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-sky-600 dark:text-sky-400 group-hover:translate-x-1 transition-transform self-end sm:self-center shrink-0">
                            <span>Pilih Pelanggan</span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                        </span>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        @else
        <div class="p-6 rounded-2xl bg-sky-50/50 dark:bg-sky-950/30 border border-sky-100 dark:border-sky-900/40 text-xs text-sky-800 dark:text-sky-300 space-y-1">
            <p class="font-bold">💡 Petunjuk Penerimaan Mandiri:</p>
            <p class="text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
                Gunakan menu ini jika pelanggan mengembalikan unit modem langsung ke kantor/gudang tanpa melalui jadwal penarikan oleh teknisi di lapangan.
            </p>
        </div>
        @endif

    {{-- =========================================================================
         LANGKAH 2: Form Konfirmasi Penerimaan Fisik Modem
         ========================================================================= --}}
    @else
    @php
        $rows = collect(old('serials', []))->map(fn ($r) => ['serial_number' => $r['serial_number'] ?? '', 'item_id' => $r['item_id'] ?? ''])->values();

        if ($rows->isEmpty()) {
            $rows = $installedSerials->map(fn ($s) => ['serial_number' => $s->serial_number, 'item_id' => $s->item_id])->values();
        }

        if ($rows->isEmpty() && ($legacyHint['serial'] ?? null)) {
            $rows = collect([['serial_number' => $legacyHint['serial'], 'item_id' => $legacyHint['item']?->id ?? '']]);
        }

        if ($rows->isEmpty()) {
            $rows = collect([['serial_number' => '', 'item_id' => '']]);
        }
    @endphp

    {{-- Selected Customer Banner --}}
    <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200/80 dark:border-slate-700/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
        <div class="space-y-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Pelanggan Terpilih</span>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-bold text-slate-900 dark:text-slate-100 text-sm sm:text-base">{{ $customer->full_name }}</span>
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                    {{ $customer->cid ?? $customer->customer_code }}
                </span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                {{ $customer->customerAddress?->full_address ?? $customer->address ?? 'Alamat tidak tersedia' }}
            </p>
        </div>
        <a href="{{ route('warehouse.returns.from-customer.create') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors self-start sm:self-center shrink-0">
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            <span>Ganti Pelanggan</span>
        </a>
    </div>

    @if($blockedReason)
    <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span class="leading-relaxed">{{ $blockedReason }}</span>
    </div>
    @else

    @if($installedSerials->isNotEmpty())
    <div class="p-3.5 rounded-xl bg-sky-50 dark:bg-sky-950/30 border border-sky-200/80 dark:border-sky-800/60 text-xs text-sky-800 dark:text-sky-300 flex items-center gap-2">
        <svg class="w-4 h-4 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span><strong class="font-bold">Perangkat terdaftar:</strong> {{ $installedSerials->map(fn ($s) => $s->serial_number.' ('.($s->item?->name ?? 'Model Belum Ditentukan').')')->join(', ') }}</span>
    </div>
    @elseif(! empty($legacyHint['serial']))
    <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Data lama mencatat SN: <strong class="font-mono font-bold">{{ $legacyHint['serial'] }}</strong>@if($legacyHint['label']) (perangkat: "{{ $legacyHint['label'] }}")@endif — cocokkan dengan stiker di modem fisik.</span>
    </div>
    @endif

    <form action="{{ route('warehouse.returns.from-customer.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5"
          x-data="{
              rows: @js($rows->all()),
              selectedCondition: '{{ old('condition', 'used_good') }}',
              photoPreview: null,
              handlePhoto(event) {
                  const file = event.target.files[0];
                  if (file) {
                      const reader = new FileReader();
                      reader.onload = (e) => { this.photoPreview = e.target.result; };
                      reader.readAsDataURL(file);
                  }
              },
              add() { this.rows.push({ serial_number: '', item_id: '' }) },
              remove(i) { if(this.rows.length > 1) this.rows.splice(i, 1) }
          }">
        @csrf
        <input type="hidden" name="customer_id" value="{{ $customer->id }}">

        <div>
            <label class="{{ $labelClass }}">Diterima di Gudang Cabang <span class="text-rose-500">*</span></label>
            <select name="cabang_pop_id" required class="{{ $inputClass }} cursor-pointer">
                <option value="">— Pilih Gudang Cabang Penerima —</option>
                @foreach($cabangPops as $pop)
                <option value="{{ $pop->id }}" @selected((string) old('cabang_pop_id') === (string) $pop->id)>{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Dynamic Serial Repeater --}}
        <div class="space-y-3 p-4 sm:p-5 rounded-2xl bg-slate-50/80 dark:bg-slate-900/50 border border-slate-200/80 dark:border-slate-700/80">
            <div>
                <label class="{{ $labelClass }}">Daftar Nomor Seri (SN) Modem yang Diterima <span class="text-rose-500">*</span></label>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-3">Isi nomor seri sesuai stiker fisik. Pilih tipe master barang jika SN belum pernah terdaftar di sistem.</p>
            </div>

            <div class="space-y-2.5">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-center bg-white dark:bg-slate-800 p-2.5 sm:p-0 rounded-xl sm:bg-transparent border sm:border-0 border-slate-200 dark:border-slate-700">
                        <div>
                            <input type="text" :name="`serials[${i}][serial_number]`" x-model="row.serial_number" maxlength="100" placeholder="Nomor Seri (SN)" class="{{ $inputClass }} font-mono uppercase font-bold" required>
                        </div>
                        <div>
                            <select :name="`serials[${i}][item_id]`" x-model="row.item_id" class="{{ $inputClass }} cursor-pointer">
                                <option value="">— Model (isi jika SN baru) —</option>
                                @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex justify-end">
                            <button type="button" @click="remove(i)" x-show="rows.length > 1" class="inline-flex items-center gap-1 text-xs font-bold px-3 py-2 text-rose-600 hover:text-rose-700 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 rounded-xl transition-colors cursor-pointer">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                <span>Hapus</span>
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" @click="add()" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-950/50 rounded-xl transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                <span>Tambah Unit Modem Lain</span>
            </button>
        </div>

        {{-- Kondisi Fisik --}}
        <div>
            <label class="{{ $labelClass }}">Kondisi Fisik Modem <span class="text-rose-500">*</span></label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-all"
                       :class="selectedCondition === 'used_good' ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-700 ring-2 ring-emerald-500/20' : 'bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 hover:bg-slate-100/60'">
                    <input type="radio" name="condition" value="used_good" x-model="selectedCondition" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                    <div>
                        <span class="block text-xs font-bold text-slate-900 dark:text-slate-100">Bekas — Kondisi Baik</span>
                        <span class="block text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Fisik normal, bersih & siap dialokasikan kembali</span>
                    </div>
                </label>

                <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-all"
                       :class="selectedCondition === 'used_damaged' ? 'bg-rose-50/50 dark:bg-rose-950/30 border-rose-300 dark:border-rose-700 ring-2 ring-rose-500/20' : 'bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 hover:bg-slate-100/60'">
                    <input type="radio" name="condition" value="used_damaged" x-model="selectedCondition" class="mt-0.5 text-rose-600 focus:ring-rose-500">
                    <div>
                        <span class="block text-xs font-bold text-slate-900 dark:text-slate-100">Bekas — Rusak</span>
                        <span class="block text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Fisik cacat/mati, butuh perbaikan atau afkir</span>
                    </div>
                </label>
            </div>
        </div>

        {{-- Foto Kondisi Alat --}}
        <div>
            <label class="{{ $labelClass }}">Foto Bukti Fisik Perangkat yang Diserahkan <span class="text-rose-500">*</span></label>
            <div class="p-4 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 space-y-3">
                <input type="file" name="condition_photo" @change="handlePhoto" accept="image/*" required class="w-full text-xs file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-sky-600 file:text-white hover:file:bg-sky-700 cursor-pointer">
                <template x-if="photoPreview">
                    <div class="relative w-36 h-28 rounded-xl overflow-hidden border border-slate-300 dark:border-slate-700 shadow-sm mt-2">
                        <img :src="photoPreview" alt="Preview Foto" class="w-full h-full object-cover">
                    </div>
                </template>
            </div>
        </div>

        {{-- Kelengkapan Aksesoris --}}
        <div>
            <label class="{{ $labelClass }}">Kelengkapan yang Ikut Diserahkan</label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                @foreach($accessoryOptions as $key => $label)
                <label class="flex items-center gap-2.5 p-3 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-900/40 hover:bg-slate-100/60 dark:hover:bg-slate-800/60 cursor-pointer transition-colors text-xs text-slate-700 dark:text-slate-200 font-semibold">
                    <input type="checkbox" name="accessories[]" value="{{ $key }}" @checked(in_array($key, old('accessories', []), true)) class="rounded text-sky-600 focus:ring-sky-500">
                    <span>{{ $label }}</span>
                </label>
                @endforeach
            </div>
        </div>

        {{-- Nilai Taksiran --}}
        <div>
            <label class="{{ $labelClass }}">Nilai Taksiran per Unit (Rp) — Opsional</label>
            <div class="relative">
                <span class="absolute left-3.5 top-2.5 text-xs font-bold text-slate-400">Rp</span>
                <input type="text" inputmode="decimal" data-rupiah name="estimated_value" value="{{ old('estimated_value') }}" placeholder="0" class="{{ $inputClass }} pl-9 font-mono">
            </div>
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Dapat dikosongkan (otomatis dihitung Rp 0 di Laporan Nilai Inventori).</p>
        </div>

        {{-- Catatan --}}
        <div>
            <label class="{{ $labelClass }}">Catatan Penyerahan (Opsional)</label>
            <textarea name="notes" rows="2" maxlength="500" class="{{ $inputClass }}" placeholder="Keterangan penyerahan oleh pelanggan atau kondisi fisik...">{{ old('notes') }}</textarea>
        </div>

        {{-- Actions --}}
        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-3">
            <a href="{{ route('warehouse.returns.index') }}" class="px-4 py-2.5 text-xs font-bold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white rounded-xl text-xs font-bold shadow-xs shadow-sky-600/25 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                <span>Terima ke Stok Gudang</span>
            </button>
        </div>
    </form>
    @endif
    @endif
</div>

@endsection
