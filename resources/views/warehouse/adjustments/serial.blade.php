@extends('layouts.app')

@section('title', 'Tandai Status Perangkat SN - Whusnet Operasional')
@section('page_title', 'Tandai Status Perangkat')

@section('content')

<x-warehouse.header active="custody" title="Tandai Status Perangkat (SN)" subtitle="Pembaruan status operasional jika perangkat modem / router rusak, hilang, atau dikarantina." />

<div class="max-w-2xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-6 sm:p-8 shadow-xs">
    <div class="mb-5 pb-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-100 dark:border-amber-800/60">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $serial->item->name }}</h3>
                <p class="text-xs font-mono font-bold text-sky-600 dark:text-sky-400">SN: {{ $serial->serial_number }}</p>
            </div>
        </div>
        <a href="{{ route('warehouse.custody.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali
        </a>
    </div>

    <div class="mb-5 p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60 flex items-center justify-between text-xs">
        <span class="text-slate-600 dark:text-slate-400 font-medium">Status saat ini:</span>
        <span class="font-bold text-slate-900 dark:text-slate-100">
            {{ $serial->status->label() }}
        </span>
    </div>

    <form action="{{ route('warehouse.adjustments.serial.store', $serial) }}" method="POST" enctype="multipart/form-data" class="space-y-4" x-data="{ newStatus: '{{ old('new_status', '') }}', evidenceRequired: false }" x-init="evidenceRequired = ['lost', 'damaged', 'scrapped'].includes(newStatus)">
        @csrf
        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Tandai Sebagai <span class="text-rose-500">*</span></label>
            <select name="new_status" required x-model="newStatus" @change="evidenceRequired = ['lost', 'damaged', 'scrapped'].includes(newStatus)"
                    class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                <option value="">— Pilih Status Baru —</option>
                <option value="lost">Hilang (Lost)</option>
                <option value="damaged">Rusak Fisik / Mati Total (Damaged)</option>
                <option value="scrapped">Dimusnahkan / Dihapus dari Aset (Scrapped)</option>
                <option value="quarantine">Karantina (Perlu Uji Lab / Servis)</option>
            </select>
        </div>

        <div x-show="evidenceRequired" x-cloak>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Bukti Fisik (Foto Kondisi / BAP Kehilangan) <span class="text-rose-500">*</span></label>
            <input type="file" name="evidence" accept="image/*" :required="evidenceRequired"
                   class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-100 dark:file:bg-amber-900/40 file:text-amber-700 dark:file:text-amber-300">
            <p class="text-[11px] text-slate-400 mt-1">Wajib buat Hilang/Rusak/Dimusnahkan — kontrol-anti-manipulasi.md §2. Karantina TIDAK wajib foto (status tahan sementara, bukan klaim rugi).</p>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Alasan Perubahan Status <span class="text-rose-500">*</span></label>
            <input type="text" name="reason" required maxlength="255" list="reason-suggestions" placeholder="mis. rusak_terjatuh, hilang_di_lapangan, tersambar_petir"
                   class="w-full text-xs font-medium px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            <datalist id="reason-suggestions">
                <option value="rusak_terjatuh">
                <option value="hilang_transit">
                <option value="tersambar_petir">
                <option value="rusak_kelembaban">
                <option value="port_pon_mati">
            </datalist>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Catatan Tambahan (Opsional)</label>
            <textarea name="notes" rows="2" placeholder="Keterangan kondisi fisik perangkat..."
                      class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></textarea>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <a href="{{ route('warehouse.custody.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-amber-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                <span>Simpan Perubahan Status</span>
            </button>
        </div>
    </form>
</div>

@endsection
