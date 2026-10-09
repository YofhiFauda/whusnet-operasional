@extends('layouts.app')

@section('title', 'Alihkan Roll Kabel - Whusnet Operasional')
@section('page_title', 'Alihkan Roll Kabel')

@section('content')

<x-warehouse.header active="custody" title="Alihkan Roll Kabel" subtitle="Pengembalian roll (bawa sisa meternya) ke gudang atau pengalihan ke teknisi lain." backUrl="{{ route('warehouse.custody.index') }}" />

<div class="max-w-2xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-6 sm:p-8 shadow-xs">
    <div class="mb-5 pb-4 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center border border-amber-100 dark:border-amber-800/60">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $roll->item->name ?? '(barang dihapus)' }}</h3>
                <p class="text-xs font-mono font-bold text-amber-600 dark:text-amber-400">Roll: {{ $roll->roll_code }}</p>
            </div>
        </div>
        <a href="{{ route('warehouse.custody.index') }}" class="text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
            ← Kembali
        </a>
    </div>

    <div class="mb-5 p-3.5 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60 flex items-center justify-between text-xs">
        <span class="text-slate-600 dark:text-slate-400 font-medium">Teknisi pemegang saat ini:</span>
        <span class="font-bold text-slate-900 dark:text-slate-100">{{ $roll->currentTechnician->name ?? '-' }}</span>
    </div>

    <div class="mb-5 p-3.5 rounded-lg bg-amber-50/60 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/40 flex items-center justify-between text-xs">
        <span class="text-amber-800 dark:text-amber-300 font-medium">Sisa meter yang ikut dialihkan:</span>
        <span class="font-bold text-amber-900 dark:text-amber-200 font-mono">{{ rtrim(rtrim(number_format((float) $roll->length_remaining, 2, ',', '.'), '0'), ',') }} / {{ rtrim(rtrim(number_format((float) $roll->length_total, 2, ',', '.'), '0'), ',') }} m</span>
    </div>

    <form action="{{ route('warehouse.reassign.roll.store', $roll) }}" method="POST" class="space-y-4">
        @csrf

        <div class="flex items-center gap-4 p-3 rounded-lg bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700/60">
            <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200 cursor-pointer">
                <input type="radio" name="action" value="return" onchange="toggleReassignFieldsRoll()" checked class="text-amber-600 focus:ring-amber-500">
                <span>Kembalikan ke Gudang Cabang</span>
            </label>
            <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200 cursor-pointer">
                <input type="radio" name="action" value="transfer" onchange="toggleReassignFieldsRoll()" class="text-amber-600 focus:ring-amber-500">
                <span>Pindah ke Teknisi Lain</span>
            </label>
        </div>

        <div id="field-return-roll">
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Gudang Cabang Tujuan <span class="text-rose-500">*</span></label>
            <select name="cabang_pop_id" required class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                <option value="">— Pilih Gudang Cabang —</option>
                @foreach($cabangPops as $pop)
                <option value="{{ $pop->id }}">{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <div id="field-transfer-roll" style="display:none">
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Teknisi Pengganti Penerima <span class="text-rose-500">*</span></label>
            <select name="new_technician_id" class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
                <option value="">— Pilih Teknisi Baru —</option>
                @foreach($technicians as $technician)
                <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Alasan Pengalihan <span class="text-rose-500">*</span></label>
            <input type="text" name="reason" required maxlength="255" list="reason-suggestions-roll" placeholder="mis. resign, cuti, rotasi, mutasi_cabang"
                   class="w-full text-xs font-medium px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all">
            <datalist id="reason-suggestions-roll">
                <option value="resign">
                <option value="cuti">
                <option value="rotasi">
                <option value="mutasi_cabang">
            </datalist>
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Catatan Tambahan (Opsional)</label>
            <textarea name="notes" rows="2" placeholder="Keterangan serah terima..."
                      class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-amber-500/20 focus:border-amber-500 transition-all"></textarea>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <a href="{{ route('warehouse.custody.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold shadow-xs shadow-amber-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                <span>Simpan Pengalihan</span>
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function toggleReassignFieldsRoll() {
    const isReturn = document.querySelector('input[name="action"]:checked').value === 'return';
    document.getElementById('field-return-roll').style.display = isReturn ? '' : 'none';
    document.getElementById('field-transfer-roll').style.display = isReturn ? 'none' : '';
    document.querySelector('[name="cabang_pop_id"]').required = isReturn;
    document.querySelector('[name="new_technician_id"]').required = ! isReturn;
}
toggleReassignFieldsRoll();
</script>
@endpush

@endsection
