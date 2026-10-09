@php $agent = $agent ?? null; @endphp

<div class="space-y-4">
    {{-- Kode Agent --}}
    <div>
        <label for="code" class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
            Kode Agent <span class="text-rose-500">*</span>
        </label>
        <div class="relative">
            <input type="text"
                   id="code"
                   name="code"
                   value="{{ old('code', $agent?->code) }}"
                   maxlength="30"
                   required
                   placeholder="Contoh: AGT-001, AGT-SIMAN, AGT-BAMBANG"
                   class="w-full px-3.5 py-2 text-xs sm:text-sm font-mono font-bold uppercase border @error('code') border-rose-400 dark:border-rose-600 focus:ring-rose-500/20 focus:border-rose-500 @else border-slate-200 dark:border-slate-700 focus:ring-sky-500/20 focus:border-sky-500 @enderror rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder:normal-case placeholder:font-sans placeholder:font-normal placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 transition-all shadow-2xs">
        </div>
        <p class="text-[11px] text-slate-400 dark:text-slate-500 mt-1">
            Kode unik rujukan agent. Maksimal 30 karakter, disarankan huruf kapital.
        </p>
        @error('code')
            <p class="text-xs text-rose-600 dark:text-rose-400 mt-1 font-medium flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ $message }}
            </p>
        @enderror
    </div>

    {{-- Nama Mitra Agent --}}
    <div>
        <label for="name" class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
            Nama Lengkap Mitra Agent <span class="text-rose-500">*</span>
        </label>
        <input type="text"
               id="name"
               name="name"
               value="{{ old('name', $agent?->name) }}"
               maxlength="150"
               required
               placeholder="Nama lengkap mitra / perorangan / kelompok"
               class="w-full px-3.5 py-2 text-xs sm:text-sm border @error('name') border-rose-400 dark:border-rose-600 focus:ring-rose-500/20 focus:border-rose-500 @else border-slate-200 dark:border-slate-700 focus:ring-sky-500/20 focus:border-sky-500 @enderror rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 transition-all shadow-2xs">
        @error('name')
            <p class="text-xs text-rose-600 dark:text-rose-400 mt-1 font-medium flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ $message }}
            </p>
        @enderror
    </div>

    {{-- Telepon / WhatsApp --}}
    <div>
        <label for="phone" class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
            Nomor Telepon / WhatsApp <span class="text-slate-400 font-normal">(Opsional)</span>
        </label>
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
            </div>
            <input type="text"
                   id="phone"
                   name="phone"
                   value="{{ old('phone', $agent?->phone) }}"
                   maxlength="20"
                   placeholder="Contoh: 081234567890"
                   class="w-full pl-9 pr-3.5 py-2 text-xs sm:text-sm font-mono border @error('phone') border-rose-400 dark:border-rose-600 focus:ring-rose-500/20 focus:border-rose-500 @else border-slate-200 dark:border-slate-700 focus:ring-sky-500/20 focus:border-sky-500 @enderror rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 transition-all shadow-2xs">
        </div>
        @error('phone')
            <p class="text-xs text-rose-600 dark:text-rose-400 mt-1 font-medium flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ $message }}
            </p>
        @enderror
    </div>

    {{-- Status Aktif Card --}}
    <div class="pt-2">
        <label class="flex items-start gap-3 p-3.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 hover:bg-slate-50 dark:hover:bg-slate-900/60 transition-colors cursor-pointer">
            <input type="checkbox"
                   name="is_active"
                   value="1"
                   {{ old('is_active', $agent?->is_active ?? true) ? 'checked' : '' }}
                   class="mt-0.5 rounded border-slate-300 dark:border-slate-600 text-sky-600 focus:ring-sky-500 focus:ring-offset-0 h-4 w-4">
            <div class="text-xs">
                <span class="font-semibold text-slate-800 dark:text-slate-200 block">
                    Mitra Agent Aktif
                </span>
                <span class="text-slate-500 dark:text-slate-400 block mt-0.5">
                    Agent yang aktif akan muncul di pilihan dropdown form pendaftaran pelanggan skema Agent.
                </span>
            </div>
        </label>
    </div>
</div>
