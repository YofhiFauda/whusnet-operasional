@extends('layouts.app')

@section('title', 'Alihkan Perangkat (SN) - Whusnet Operasional')
@section('page_title', 'Alihkan Perangkat (SN)')

@section('content')

<x-warehouse.header active="custody" title="Alihkan Perangkat Aktif (SN)" subtitle="Pengalihan kepemilikan perangkat serial number dari teknisi lama ke teknisi baru atau pengembalian ke gudang." />

<div class="max-w-2xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-6 sm:p-8 shadow-xs">
    <div class="mb-5 pb-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-sky-50 dark:bg-sky-950/50 text-sky-600 dark:text-sky-400 flex items-center justify-center border border-sky-100 dark:border-sky-800/60">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/>
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
        <span class="text-slate-600 dark:text-slate-400 font-medium">Teknisi pemegang saat ini:</span>
        <span class="font-bold text-slate-900 dark:text-slate-100">
            {{ $serial->currentTechnician->name ?? '-' }}
        </span>
    </div>

    <form action="{{ route('warehouse.reassign.serial.store', $serial) }}" method="POST" class="space-y-4">
        @csrf

        <div class="flex items-center gap-4 p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60">
            <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200 cursor-pointer">
                <input type="radio" name="action" value="return" onchange="toggleReassignFieldsSerial()" checked class="text-sky-600 focus:ring-sky-500">
                <span>Kembalikan ke Gudang Cabang</span>
            </label>
            <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200 cursor-pointer">
                <input type="radio" name="action" value="transfer" onchange="toggleReassignFieldsSerial()" class="text-sky-600 focus:ring-sky-500">
                <span>Pindah ke Teknisi Lain</span>
            </label>
        </div>

        <div id="field-return-serial">
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Gudang Cabang Tujuan <span class="text-rose-500">*</span></label>
            <select name="cabang_pop_id" required class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">— Pilih Gudang Cabang —</option>
                @foreach($cabangPops as $pop)
                <option value="{{ $pop->id }}">{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <div id="field-transfer-serial" style="display:none">
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Teknisi Pengganti Penerima <span class="text-rose-500">*</span></label>
            <select name="new_technician_id" class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">— Pilih Teknisi Baru —</option>
                @foreach($technicians as $technician)
                <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Alasan Pengalihan <span class="text-rose-500">*</span></label>
            <input type="text" name="reason" required maxlength="255" list="reason-suggestions" placeholder="mis. resign, cuti, rotasi, mutasi_cabang"
                   class="w-full text-xs font-medium px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
            <datalist id="reason-suggestions">
                <option value="resign">
                <option value="cuti">
                <option value="rotasi">
                <option value="mutasi_cabang">
            </datalist>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Catatan Tambahan (Opsional)</label>
            <textarea name="notes" rows="2" placeholder="Keterangan serah terima..."
                      class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all"></textarea>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <a href="{{ route('warehouse.custody.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-xs font-bold shadow-xs shadow-sky-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                <span>Simpan Pengalihan</span>
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function toggleReassignFieldsSerial() {
    const isReturn = document.querySelector('input[name="action"]:checked').value === 'return';
    document.getElementById('field-return-serial').style.display = isReturn ? '' : 'none';
    document.getElementById('field-transfer-serial').style.display = isReturn ? 'none' : '';
    document.querySelector('[name="cabang_pop_id"]').required = isReturn;
    document.querySelector('[name="new_technician_id"]').required = ! isReturn;
}
toggleReassignFieldsSerial();
</script>
@endpush

@endsection
