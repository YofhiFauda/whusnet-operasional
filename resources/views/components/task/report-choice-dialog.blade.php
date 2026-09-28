@props([
    'task',
    'reportUrl',
])

@php
    $dialogName = 'report-choice-' . $task->id;
    $reasonModalName = 'report-choice-later-' . $task->id;
@endphp

<button type="button" x-data @click="$dispatch('open-modal', '{{ $dialogName }}')"
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer']) }}
        style="background:var(--color-success)">
    {{ $slot }}
</button>

<x-ui.modal name="{{ $dialogName }}" title="Laporan Task" maxWidth="sm">
    <p class="text-xs text-text-secondary mb-4 leading-relaxed font-ui">
        Isi laporan sekarang, atau tunda dulu dan lanjutkan nanti — task tetap terdaftar ke Anda.
    </p>
    <div class="flex flex-col gap-2 font-ui">
        <a href="{{ $reportUrl }}"
           class="inline-flex items-center justify-center gap-1.5 text-xs font-semibold px-4 py-2.5 rounded text-white transition-colors"
           style="background:var(--color-success)">
            Lapor Sekarang
        </a>
        <button type="button"
                x-on:click="$dispatch('close-modal', '{{ $dialogName }}'); $dispatch('open-modal', '{{ $reasonModalName }}')"
                class="inline-flex items-center justify-center gap-1.5 text-xs font-semibold px-4 py-2.5 rounded border transition-colors bg-surface cursor-pointer"
                style="border-color:var(--color-warning-border); color:var(--color-warning)">
            Lapor Nanti
        </button>
    </div>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', '{{ $dialogName }}')">
            Batal
        </x-ui.button>
    </x-slot>
</x-ui.modal>

<x-ui.modal name="{{ $reasonModalName }}" title="Lapor Nanti" maxWidth="sm">
    <p class="text-xs text-text-secondary mb-3 leading-relaxed font-ui">
        Pekerjaan dianggap <span class="font-semibold text-text-main">sudah selesai di lapangan</span>, laporannya menyusul. Status task jadi <span class="font-semibold text-text-main">Lapor Nanti</span> — tetap terdaftar ke Anda, tidak masuk antrian jadwal ulang FOP. Lanjutkan laporannya kapan saja dari Tasks Saya.
    </p>
    <form id="form-{{ $reasonModalName }}" action="{{ route('tasks.report-later', $task) }}" method="POST">
        @csrf
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">
                Alasan Menunda Laporan <span class="text-error">*</span>
            </label>
            <x-ui.textarea name="pending_reason" rows="3" maxlength="255" placeholder="Contoh: Kendala sinyal di lokasi..." required />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', '{{ $reasonModalName }}')">
            Batal
        </x-ui.button>
        <x-ui.button type="submit" form="form-{{ $reasonModalName }}" variant="warning">
            Konfirmasi Lapor Nanti
        </x-ui.button>
    </x-slot>
</x-ui.modal>
