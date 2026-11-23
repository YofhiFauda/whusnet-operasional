{{--
    Color picker warna badge Kategori Issue — dipakai create & edit.
    Param: $value (hex tersimpan / old input, boleh null), $name (nama kategori buat preview).

    Kosong = "Otomatis": Worksheet Helpdesk tetap nebak warna dari kata kunci
    nama kategori (issueBadgeClass()), jadi kategori lama gak berubah tampilan.
    Nilai dikirim lewat <input type="hidden">, BUKAN langsung dari
    <input type="color"> — input color gak bisa bernilai kosong, padahal
    "Otomatis" harus bisa dipilih balik.
--}}
@php
    $presets = [
        '#e11d48' => 'Merah',
        '#f97316' => 'Oranye',
        '#f59e0b' => 'Kuning',
        '#10b981' => 'Hijau',
        '#0ea5e9' => 'Biru Muda',
        '#4f46e5' => 'Indigo',
        '#9333ea' => 'Ungu',
        '#db2777' => 'Pink',
        '#475569' => 'Abu-abu',
    ];
@endphp
<div x-data="{
        color: @js($value ?? ''),
        label: @js(filled($name ?? null) ? $name : 'Contoh Kategori'),
        get isValid() { return /^#[0-9a-f]{6}$/i.test(this.color); },
        // Cermin TicketIssueCategory::textColorFor() — preview harus sama dengan hasil server.
        get textColor() {
            if (!this.isValid) return null;
            const lin = c => c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
            const [r, g, b] = [1, 3, 5].map(i => lin(parseInt(this.color.substr(i, 2), 16) / 255));
            return (0.2126 * r + 0.7152 * g + 0.0722 * b) > 0.45 ? '#0f172a' : '#ffffff';
        },
     }"
     x-init="document.getElementById('name')?.addEventListener('input', e => label = e.target.value || 'Contoh Kategori')"
     class="pt-2">
    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wide mb-1.5">Warna Badge</label>
    <input type="hidden" name="badge_color" :value="color">

    <div class="flex flex-wrap items-center gap-2">
        @foreach($presets as $hex => $presetLabel)
            <button type="button" @click="color = '{{ $hex }}'" title="{{ $presetLabel }}"
                    class="h-7 w-7 rounded-full border-2 transition-transform hover:scale-110 cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-1 dark:focus:ring-offset-slate-800"
                    :class="color.toLowerCase() === '{{ $hex }}' ? 'border-slate-900 dark:border-white scale-110' : 'border-transparent'"
                    style="background-color: {{ $hex }}">
                <span class="sr-only">{{ $presetLabel }}</span>
            </button>
        @endforeach

        <label class="relative h-7 w-7 rounded-full border-2 border-dashed border-slate-300 dark:border-slate-600 cursor-pointer overflow-hidden flex items-center justify-center" title="Warna lain">
            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
            <input type="color" class="absolute inset-0 opacity-0 cursor-pointer"
                   :value="isValid ? color : '#0ea5e9'" @input="color = $event.target.value">
        </label>

        <input type="text" x-model="color" maxlength="7" placeholder="#0ea5e9"
               class="w-24 px-2 py-1 border @error('badge_color') border-rose-500 @else border-slate-300 dark:border-slate-600 @enderror rounded-md text-xs font-mono text-slate-800 dark:text-slate-200 bg-white dark:bg-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500">

        <button type="button" @click="color = ''" x-show="color !== ''"
                class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 hover:text-rose-600 cursor-pointer">
            Kosongkan (Otomatis)
        </button>
    </div>

    <div class="mt-3 flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400">
        <span>Preview:</span>
        <span x-show="isValid" x-text="label" class="px-2 py-0.5 rounded text-[10px] font-bold"
              :style="`background-color: ${color}; color: ${textColor}`"></span>
        <span x-show="!isValid" class="italic">Otomatis — warna ditebak dari nama kategori</span>
    </div>
    @error('badge_color')
        <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
    @enderror
</div>
