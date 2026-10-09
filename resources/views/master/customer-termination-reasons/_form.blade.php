{{-- Field form Master Alasan Putus Langganan — dipakai create & edit.
     `$reason` null di halaman create. --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label for="name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Nama Alasan <span class="text-rose-500">*</span></label>
        <input type="text" name="name" id="name" value="{{ old('name', $reason?->name) }}" required maxlength="150" placeholder="Contoh: Pindah, Kompetitor, Meninggal"
               class="w-full px-3 py-2 border @error('name') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
        @error('name')
            <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="default_penalty_amount" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Nilai Awal Denda</label>
        <input type="text" inputmode="decimal" name="default_penalty_amount" id="default_penalty_amount" data-rupiah
               value="{{ old('default_penalty_amount', $reason ? (int) $reason->default_penalty_amount : 0) }}" placeholder="0"
               class="w-full px-3 py-2 border @error('default_penalty_amount') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm font-mono text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
        <p class="text-[10px] text-slate-400 mt-1">Cuma titik awal/prefill di form Putus Langganan untuk pelanggan masa langganan &le;1 tahun — admin tetap wajib konfirmasi/ubah nilainya, tidak otomatis terpakai untuk semua pelanggan.</p>
        @error('default_penalty_amount')
            <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
    <div>
        @php $activeValue = (string) old('is_active', $reason ? ($reason->is_active ? '1' : '0') : '1'); @endphp
        <label for="is_active" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Status <span class="text-rose-500">*</span></label>
        <select name="is_active" id="is_active" required
                class="w-full px-3 py-2 border @error('is_active') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 bg-white dark:bg-slate-800">
            <option value="1" {{ $activeValue === '1' ? 'selected' : '' }}>Aktif</option>
            <option value="0" {{ $activeValue === '0' ? 'selected' : '' }}>Nonaktif</option>
        </select>
        <p class="text-[10px] text-slate-400 mt-1">Nonaktif = hilang dari dropdown form Putus Langganan baru, data pelanggan lama yang sudah memakainya tetap utuh.</p>
        @error('is_active')
            <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
        @enderror
    </div>
</div>
