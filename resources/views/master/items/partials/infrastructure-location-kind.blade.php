{{-- Posisi Pemasangan aset berserial (rancangan hierarki infrastruktur §10.4/§16.1). --}}
<div class="pt-2">
    <div class="mb-1.5 flex items-center gap-1.5">
        <label for="infrastructure_location_kind" class="text-xs font-bold uppercase tracking-wide text-slate-700 dark:text-slate-300">Posisi Pemasangan</label>
        <details class="group relative">
            <summary aria-label="Penjelasan Posisi Pemasangan" class="flex size-4 cursor-pointer list-none items-center justify-center rounded-full border border-slate-400 text-[10px] font-bold leading-none text-slate-500 hover:border-sky-500 hover:text-sky-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500 dark:border-slate-500 dark:text-slate-400 [&::-webkit-details-marker]:hidden">?</summary>
            <div class="absolute left-0 top-full z-30 mt-2 w-64 rounded-md border border-slate-200 bg-white p-3 text-xs font-normal normal-case leading-relaxed tracking-normal text-slate-700 shadow-lg dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                Tingkat tempat SN barang ini boleh dipasang lewat laporan O-REQ/INFR-REQ. Contoh: rak di POP/Cabang, kartu OLT di Slot, SFP di PON/Port. Kosong = node/perangkat. Aset Perusahaan (Switch/SFP) wajib diisi supaya boleh dipasang netap.
            </div>
        </details>
    </div>
    <select name="infrastructure_location_kind" id="infrastructure_location_kind" class="w-full sm:w-1/2 px-3 py-2 rounded-md border border-slate-300 bg-white text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
        <option value="">Node/perangkat (bawaan)</option>
        @foreach(\App\Enums\InfrastructureLocationKind::cases() as $locationKind)
            <option value="{{ $locationKind->value }}" @selected($selectedLocationKind === $locationKind->value)>{{ $locationKind->label() }}</option>
        @endforeach
    </select>
    @error('infrastructure_location_kind')<p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>@enderror
</div>
