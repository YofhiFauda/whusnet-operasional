{{-- Field form Master Rekening Bank — dipakai create & edit. `$account`
     null di halaman create. --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label for="bank_name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Nama Bank <span class="text-rose-500">*</span></label>
        <input type="text" name="bank_name" id="bank_name" value="{{ old('bank_name', $account?->bank_name) }}" required maxlength="100" placeholder="Contoh: BCA"
               class="w-full px-3 py-2 border @error('bank_name') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
        @error('bank_name')
            <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="account_number" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Nomor Rekening <span class="text-rose-500">*</span></label>
        <input type="text" inputmode="numeric" name="account_number" id="account_number" value="{{ old('account_number', $account?->account_number) }}" required maxlength="50" placeholder="Contoh: 1234567890"
               class="w-full px-3 py-2 border @error('account_number') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm font-mono text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
        @error('account_number')
            <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label for="account_holder_name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Atas Nama <span class="text-rose-500">*</span></label>
        <input type="text" name="account_holder_name" id="account_holder_name" value="{{ old('account_holder_name', $account?->account_holder_name) }}" required maxlength="150" placeholder="Nama pemilik rekening"
               class="w-full px-3 py-2 border @error('account_holder_name') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
        @error('account_holder_name')
            <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="label" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Label</label>
        <input type="text" name="label" id="label" value="{{ old('label', $account?->label) }}" maxlength="100" placeholder="Opsional, mis. BCA Utama"
               class="w-full px-3 py-2 border @error('label') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1">
        @error('label')
            <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
    <div>
        @php $activeValue = (string) old('is_active', $account ? ($account->is_active ? '1' : '0') : '1'); @endphp
        <label for="is_active" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Status <span class="text-rose-500">*</span></label>
        <select name="is_active" id="is_active" required
                class="w-full px-3 py-2 border @error('is_active') border-rose-500 focus:ring-rose-500 @else border-slate-300 dark:border-slate-600 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 bg-white dark:bg-slate-800">
            <option value="1" {{ $activeValue === '1' ? 'selected' : '' }}>Aktif</option>
            <option value="0" {{ $activeValue === '0' ? 'selected' : '' }}>Nonaktif</option>
        </select>
        @error('is_active')
            <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
        @enderror
    </div>
</div>
