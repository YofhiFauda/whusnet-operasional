@extends('layouts.app')

@section('title', 'Ajukan Surat Pengajuan Barang - Whusnet Operasional')
@section('page_title', 'Ajukan Surat Pengajuan Barang')

@section('content')

<x-warehouse.header active="pengajuan-barang" title="Ajukan Surat Pengajuan Barang" subtitle="Buat pengajuan barang yang belum ada di gudang — nama barang/spesifikasi diketik bebas." backUrl="{{ route('warehouse.pengajuan-barang.index') }}" />

<div class="max-w-3xl bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-6 sm:p-8 shadow-xs">
    <form action="{{ route('warehouse.pengajuan-barang.store') }}" method="POST" class="space-y-5" x-data="pengajuanBarangForm()">
        @csrf

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">Nama Kegiatan <span class="text-rose-500">*</span></label>
            <input type="text" name="nama_kegiatan" value="{{ old('nama_kegiatan') }}" required placeholder="mis. Penambahan POP Bandar, Pacitan (Baru)"
                   class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
        </div>

        <div>
            <label class="block mb-1.5 text-xs font-bold text-slate-700 dark:text-slate-200">OLT / Cabang / POP <span class="text-rose-500">*</span></label>
            <select name="pop_id" required class="w-full text-xs font-semibold px-3 py-2.5 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all">
                <option value="">— Pilih Cabang/POP —</option>
                @foreach($pops as $pop)
                <option value="{{ $pop->id }}" {{ old('pop_id') == $pop->id ? 'selected' : '' }}>{{ $pop->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label class="text-xs font-bold text-slate-700 dark:text-slate-200">Barang yang Diajukan <span class="text-rose-500">*</span></label>
            </div>

            <template x-for="(row, index) in rows" :key="index">
                <div class="bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/50 rounded-lg p-3 mb-2.5 flex flex-wrap items-end gap-2">
                    <div class="flex-1 min-w-[10rem]">
                        <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Nama Barang</label>
                        <input type="text" :name="`lines[${index}][nama_barang]`" x-model="row.nama_barang" required placeholder="mis. OLT Gpon ZTE C320"
                               class="w-full text-xs px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500">
                    </div>
                    <div class="flex-1 min-w-[10rem]">
                        <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Spesifikasi</label>
                        <input type="text" :name="`lines[${index}][spesifikasi]`" x-model="row.spesifikasi" placeholder="opsional"
                               class="w-full text-xs px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500">
                    </div>
                    <div class="w-24">
                        <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Jumlah</label>
                        <input type="number" step="0.01" min="0.01" :name="`lines[${index}][jumlah]`" x-model="row.jumlah" required
                               class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500">
                    </div>
                    <div class="w-24">
                        <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Satuan</label>
                        <input type="text" :name="`lines[${index}][satuan]`" x-model="row.satuan" required placeholder="Unit/Meter"
                               class="w-full text-xs px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500">
                    </div>
                    <div class="w-full sm:w-48">
                        <label class="block mb-1 text-[10px] uppercase tracking-wide text-slate-500 dark:text-slate-400">Keterangan (Link)</label>
                        <input type="url" :name="`lines[${index}][keterangan_link]`" x-model="row.keterangan_link" placeholder="https://..."
                               class="w-full text-xs font-mono px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:border-sky-500">
                    </div>
                    <button type="button" @click="rows.splice(index, 1)" class="text-red-500 hover:text-red-700 p-1.5 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors shrink-0" title="Hapus baris">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                    </button>
                </div>
            </template>

            <button type="button" @click="rows.push({nama_barang: '', spesifikasi: '', jumlah: '', satuan: '', keterangan_link: ''})" class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 hover:bg-sky-100 dark:hover:bg-sky-900/50 px-3 py-1.5 rounded-md transition-colors">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Tambah Barang
            </button>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
            <a href="{{ route('warehouse.pengajuan-barang.index') }}" class="px-4 py-2.5 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                Batal
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-bold shadow-xs shadow-sky-600/20 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                <span>Ajukan Barang</span>
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function pengajuanBarangForm() {
    return {
        rows: [{ nama_barang: '', spesifikasi: '', jumlah: '', satuan: '', keterangan_link: '' }],
    };
}
</script>
@endpush

@endsection
