@extends('layouts.app')

@section('title', 'Terima Retur Modem - Whusnet Operasional')
@section('page_title', 'Terima Retur Modem')

@section('content')

<x-warehouse.header active="returns" title="Konfirmasi Terima Retur Modem" subtitle="Terima custody fisik dari teknisi. Belum jadi stok — kondisi final & stok baru ditentukan Gudang Pusat setelah dikirim (menu Kirim ke Pusat)." backUrl="{{ route('warehouse.returns.index') }}" />

@php
    $photoPath = $retrievalLog?->photoPath() ?? $taskDeviceRetrieval?->condition_photo;
    $accessories = $taskDeviceRetrieval?->accessories ?? $retrievalLog?->accessories ?? [];
    $accessoryLabels = \App\Models\TaskDeviceRetrieval::ACCESSORY_OPTIONS;
@endphp

<div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

    {{-- =========================================================================
         KOLOM KIRI: Laporan Pengembalian Lengkap dari Teknisi di Lapangan
         ========================================================================= --}}
    <div class="lg:col-span-7 space-y-5">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 sm:p-7 shadow-xs space-y-6">
            {{-- Card Header --}}
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </span>
                    <div>
                        <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100">Laporan Pengembalian dari Lapangan</h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">Bukti serah terima dan kondisi perangkat saat penarikan oleh teknisi</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border border-amber-200 dark:border-amber-800/60">
                    <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                    <span>Transit Teknisi</span>
                </span>
            </div>

            {{-- Info Petugas & Gudang --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div class="p-4 rounded-xl bg-slate-50/80 dark:bg-slate-900/50 border border-slate-200/60 dark:border-slate-700/50 text-xs">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1.5">Teknisi Pengambil</p>
                    <p class="font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2 text-sm">
                        <span class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-xs font-bold text-slate-700 dark:text-slate-200">
                            {{ strtoupper(substr($retrievalLog?->retrievedBy?->name ?? $serial->currentTechnician?->name ?? 'T', 0, 1)) }}
                        </span>
                        <span>{{ $retrievalLog?->retrievedBy?->name ?? $serial->currentTechnician?->name ?? 'Teknisi Lapangan' }}</span>
                    </p>
                    @if($retrievalLog?->retrieved_at)
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 pl-8">
                        {{ $retrievalLog->retrieved_at->translatedFormat('d F Y, H:i') }} WIB
                    </p>
                    @endif
                </div>

                <div class="p-4 rounded-xl bg-slate-50/80 dark:bg-slate-900/50 border border-slate-200/60 dark:border-slate-700/50 text-xs">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1.5">Gudang Cabang Tujuan</p>
                    <p class="font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2 text-sm">
                        <svg class="w-5 h-5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                        <span>{{ $serial->issuedFromPop?->name ?? 'Gudang Utama' }}</span>
                    </p>
                    @if($retrievalLog?->task)
                    <p class="text-[11px] text-sky-600 dark:text-sky-400 mt-1 font-mono font-bold">
                        Task Penarikan: #{{ $retrievalLog->task->task_number }}
                    </p>
                    @endif
                </div>
            </div>

            {{-- Detail Pelanggan Asal --}}
            <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/80 dark:bg-slate-900/50 border border-slate-200/60 dark:border-slate-700/50 text-xs space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200/60 dark:border-slate-700/40">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span>Pelanggan Asal (Putus Langganan)</span>
                    </span>
                    @if($serial->customer)
                    <a href="{{ route('customers.show', $serial->customer) }}" target="_blank" class="text-xs font-bold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 inline-flex items-center gap-1">
                        <span>Profil Pelanggan</span>
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                    </a>
                    @endif
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block mb-0.5">Nama Lengkap</span>
                        <span class="font-bold text-slate-900 dark:text-slate-100 text-sm block">{{ $serial->customer?->full_name ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block mb-0.5">CID / No. Pelanggan</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200 text-xs px-2 py-0.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 inline-block">
                            {{ $serial->customer?->cid ?? $serial->customer?->customer_code ?? '-' }}
                        </span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block mb-0.5">Nomor HP / Kontak</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-200 text-xs">{{ $serial->customer?->phone_number ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-slate-400 text-[10px] font-bold uppercase tracking-wider block mb-0.5">Alamat Pemasangan</span>
                        <span class="text-slate-700 dark:text-slate-300 text-xs leading-relaxed block">
                            {{ $serial->customer?->customerAddress?->full_address ?? $serial->customer?->address ?? '-' }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Identitas Modem yang Ditarik --}}
            <div class="p-4 sm:p-5 rounded-2xl bg-sky-500/5 dark:bg-sky-500/10 border border-sky-200/80 dark:border-sky-800/60 space-y-2">
                <span class="text-[10px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400 block">Identitas Modem Terdaftar</span>
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div>
                        <p class="font-mono font-extrabold text-base sm:text-lg text-slate-900 dark:text-white">{{ $serial->serial_number }}</p>
                        <p class="text-xs text-slate-600 dark:text-slate-300 font-medium mt-0.5">
                            Model Master: <span class="font-bold text-slate-800 dark:text-slate-100">{{ $serial->item?->name ?? 'Belum ditentukan' }}</span>
                        </p>
                    </div>
                    <x-warehouse.origin-badge :log="$retrievalLog" />
                </div>
                @if($legacyHint && $legacyHint['label'])
                <div class="mt-3 pt-2.5 border-t border-sky-200/60 dark:border-sky-800/60 text-xs text-amber-800 dark:text-amber-300 bg-amber-50/50 dark:bg-amber-950/30 p-2.5 rounded-xl">
                    <span class="font-bold">⚠️ Catatan Data Migrasi:</span> Perangkat tercatat "{{ $legacyHint['label'] }}"@if($legacyHint['item']) (kemungkinan master: <span class="font-semibold">{{ $legacyHint['item']->name }}</span>)@endif.
                </div>
                @endif
            </div>

            {{-- Kelengkapan yang Diserahkan --}}
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Kelengkapan / Aksesoris dari Lapangan</label>
                @if(!empty($accessories) && count($accessories) > 0)
                <div class="flex flex-wrap gap-2">
                    @foreach($accessories as $accKey)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        <span>{{ $accessoryLabels[$accKey] ?? ucfirst(str_replace('_', ' ', $accKey)) }}</span>
                    </span>
                    @endforeach
                </div>
                @else
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-xs text-slate-400 italic">
                    Hanya unit modem (tidak ada adaptor/kabel tambahan yang dicatat).
                </div>
                @endif
            </div>

            {{-- Catatan Teknisi --}}
            <div class="space-y-1.5">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Catatan Lapangan Teknisi</label>
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 text-xs text-slate-700 dark:text-slate-300 italic leading-relaxed">
                    "{{ $taskDeviceRetrieval?->notes ?? $retrievalLog?->notes ?? 'Tidak ada catatan khusus dari teknisi.' }}"
                </div>
            </div>

            {{-- Foto Kondisi Alat dari Lapangan --}}
            <div class="space-y-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Foto Bukti Fisik Lapangan</label>
                @if($photoPath)
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <a href="{{ Storage::disk('public')->url($photoPath) }}" target="_blank" rel="noopener"
                       class="group relative block w-full sm:w-44 h-32 rounded-xl overflow-hidden border border-slate-300 dark:border-slate-700 shadow-sm shrink-0 bg-slate-200 dark:bg-slate-800">
                        <img src="{{ Storage::disk('public')->url($photoPath) }}" alt="Foto Kondisi Alat" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs font-bold transition-opacity">
                            Buka Ukuran Penuh ↗
                        </div>
                    </a>
                    <div class="space-y-1.5 text-xs flex-1">
                        <p class="font-bold text-slate-800 dark:text-slate-200 text-sm">Dokumentasi Pengambilan</p>
                        <p class="text-slate-500 dark:text-slate-400 text-xs leading-relaxed">
                            Foto asli diambil oleh teknisi di lokasi pelanggan saat penyelesaian task penarikan alat.
                        </p>
                        <a href="{{ Storage::disk('public')->url($photoPath) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-xs font-bold text-sky-600 dark:text-sky-400 hover:underline pt-1">
                            <span>Perbesar Gambar</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                        </a>
                    </div>
                </div>
                @else
                <div class="p-6 text-center rounded-2xl bg-slate-50 dark:bg-slate-900/40 border border-dashed border-slate-300 dark:border-slate-700 text-xs text-slate-400">
                    Tidak ada foto kondisi alat yang terlampir dari laporan teknisi.
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- =========================================================================
         KOLOM KANAN: Form Konfirmasi Penerimaan Fisik di Gudang Cabang
         ========================================================================= --}}
    <div class="lg:col-span-5 space-y-5 lg:sticky lg:top-6">
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-5 sm:p-7 shadow-xs space-y-5">
            <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-700/60">
                <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-sky-500/10 text-sky-600 dark:text-sky-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </span>
                <div>
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-slate-100">Konfirmasi Terima Gudang</h2>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Cek fisik aktual & masukkan ke stok gudang</p>
                </div>
            </div>

            @if(session('error'))
            <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-300 flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
            @endif

            @if($errors->any())
            <div class="p-3.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-300 space-y-1">
                @foreach($errors->all() as $error)
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                    <span>{{ $error }}</span>
                </div>
                @endforeach
            </div>
            @endif

            <form action="{{ route('warehouse.returns.receive.store', $serial) }}" method="POST" class="space-y-4"
                  x-data="{ selectedCondition: '{{ old('condition', 'used_good') }}' }">
                @csrf

                <div>
                    <label class="block mb-2 text-xs font-bold text-slate-700 dark:text-slate-200">
                        Kondisi Fisik Hasil Pengecekan <span class="text-rose-500">*</span>
                    </label>
                    <div class="space-y-2.5">
                        <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-all"
                               :class="selectedCondition === 'used_good' ? 'bg-emerald-50/50 dark:bg-emerald-950/30 border-emerald-300 dark:border-emerald-700 ring-2 ring-emerald-500/20' : 'bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 hover:bg-slate-100/60'">
                            <input type="radio" name="condition" value="used_good" x-model="selectedCondition" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <span class="block text-xs font-bold text-slate-900 dark:text-slate-100">Bekas — Kondisi Baik (Ready)</span>
                                <span class="block text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Fisik bersih, port normal, dan layak dialokasikan kembali ke pelanggan baru.</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition-all"
                               :class="selectedCondition === 'used_damaged' ? 'bg-rose-50/50 dark:bg-rose-950/30 border-rose-300 dark:border-rose-700 ring-2 ring-rose-500/20' : 'bg-slate-50/50 dark:bg-slate-900/40 border-slate-200 dark:border-slate-700 hover:bg-slate-100/60'">
                            <input type="radio" name="condition" value="used_damaged" x-model="selectedCondition" class="mt-0.5 text-rose-600 focus:ring-rose-500">
                            <div>
                                <span class="block text-xs font-bold text-slate-900 dark:text-slate-100">Bekas — Rusak / Afkir</span>
                                <span class="block text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Fisik pecah, port mati, adaptor rusak, atau perlu servis sebelum bisa dipakai.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Koreksi Model Master (Opsional)</label>
                    <select name="item_id" class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all cursor-pointer">
                        <option value="">— Tetap: {{ $serial->item?->name ?? 'Pilih Model' }} —</option>
                        @foreach($items as $item)
                        <option value="{{ $item->id }}" @selected((string) old('item_id') === (string) $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Pilih jika tipe modem aktual di tangan berbeda dari pencatatan lama.</p>
                </div>

                <div>
                    <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Nilai Taksiran Perangkat (Rp) — Opsional</label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-xs font-bold text-slate-400">Rp</span>
                        <input type="text" inputmode="decimal" data-rupiah name="estimated_value" value="{{ old('estimated_value') }}" placeholder="0"
                               class="w-full text-xs font-semibold pl-9 pr-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all font-mono">
                    </div>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Bisa dikosongkan (otomatis dihitung Rp 0 di Laporan Nilai Inventori).</p>
                </div>

                <div>
                    <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Catatan Penerimaan Gudang (Opsional)</label>
                    <textarea name="notes" rows="3" maxlength="500" placeholder="Keterangan kondisi rak, nomor kardus, atau pengecekan fisik..."
                              class="w-full text-xs px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all placeholder:text-slate-400">{{ old('notes') }}</textarea>
                </div>

                <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between gap-3">
                    <a href="{{ route('warehouse.returns.index') }}" class="px-4 py-2.5 text-xs font-bold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        Batal
                    </a>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-sky-600 hover:bg-sky-700 active:bg-sky-800 text-white rounded-xl text-xs font-bold shadow-xs shadow-sky-600/25 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                        <span>Konfirmasi Terima ke Gudang</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
