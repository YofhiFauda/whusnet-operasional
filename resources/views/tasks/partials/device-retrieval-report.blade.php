{{--
    Detail Laporan task Ambil Modem (DEAC) — ADHOC-88. Sebelumnya task DEAC jatuh
    ke blok "Laporan Pekerjaan Teknisi" milik Maintenance (kendala teknis,
    material terpakai, foto OPM & speedtest) yang tidak relevan untuk pencabutan
    alat, sehingga laporan pengambilan alat tidak tampil sama sekali.

    Sumber: `task_device_retrievals` (hasil, foto, kelengkapan, catatan) dan
    `device_retrieval_logs` (per SN: teknisi, transit/diterima, kondisi akhir).
    Tampil hanya setelah teknisi mengirim laporan.
--}}
@php
    $retrieval = $task->deviceRetrieval;
    $retrievalLogs = $retrieval
        ? \App\Models\DeviceRetrievalLog::where('task_id', $task->id)
            ->with(['item', 'receivedBy', 'warehousePop'])
            ->orderBy('id')
            ->get()
        : collect();
    $outcome = $retrieval?->outcome;
    $outcomeTone = match ($outcome) {
        \App\Enums\DeviceRetrievalOutcome::DIAMBIL => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800/60',
        \App\Enums\DeviceRetrievalOutcome::TIDAK_DITEMUKAN => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800/60',
        default => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800/60',
    };
    $conditionPhotoUrl = $retrieval ? foto_publik($retrieval->condition_photo) : null;
    $accessoryLabels = collect($retrieval?->accessories ?? [])
        ->map(fn ($key) => \App\Models\TaskDeviceRetrieval::ACCESSORY_OPTIONS[$key] ?? $key)
        ->values();
@endphp

@if($retrieval)
<div class="pt-5 border-t border-border space-y-4 select-text">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-1 select-none">
        <div class="flex items-center gap-2">
            <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Laporan Pengambilan Alat</h3>
        </div>
        <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border {{ $outcomeTone }}">{{ $outcome->label() }}</span>
    </div>

    <p class="text-[11px] text-text-muted font-ui select-none">
        Dilaporkan oleh <b class="text-text-secondary">{{ $task->completedBy?->name ?? '-' }}</b>
        @if($retrieval->updated_at) · {{ $retrieval->updated_at->translatedFormat('d M Y H:i') }} WIB @endif
    </p>

    @if($outcome->isRetrieved())
    {{-- Modem yang dibawa: satu kartu per SN, dengan posisinya sekarang (transit / sudah diterima gudang) --}}
    <div>
        <span class="block text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui mb-2 select-none">Modem yang Dibawa</span>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            @forelse($retrievalLogs as $log)
            <div class="bg-surface-muted border border-border p-3.5 rounded-xl shadow-xs">
                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">{{ $log->item?->name ?? 'Modem' }}</span>
                <p class="text-xs text-sky-600 dark:text-sky-400 font-mono mt-1 font-bold select-all">SN: {{ $log->serial_number }}</p>
                <div class="mt-2 pt-2 border-t border-border text-[11px] font-ui font-semibold">
                    @if($log->isReceived())
                    <span class="text-emerald-600 dark:text-emerald-400">Sudah diterima gudang</span>
                    <span class="block text-text-muted font-medium">{{ $log->warehousePop?->name }} · {{ $log->receivedBy?->name ?? '-' }} · {{ $log->received_at->translatedFormat('d M Y') }}</span>
                    @if($log->condition)<span class="block text-text-muted font-medium">Kondisi: {{ $log->condition->label() }}</span>@endif
                    @else
                    <span class="text-amber-600 dark:text-amber-400">Transit — masih dipegang teknisi</span>
                    <span class="block text-text-muted font-medium">Menunggu diterima gudang{{ $log->warehousePop ? ': '.$log->warehousePop->name : '' }}</span>
                    @endif
                </div>
            </div>
            @empty
            <p class="text-[11px] text-text-muted font-ui">Belum ada SN tercatat untuk laporan ini.</p>
            @endforelse
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        <div class="bg-surface-muted border border-border p-3.5 rounded-xl shadow-xs">
            <span class="block text-[9px] text-text-muted font-bold uppercase mb-1.5 font-ui select-none">Kelengkapan yang Ikut Dibawa</span>
            @if($accessoryLabels->isNotEmpty())
            <ul class="list-disc list-inside space-y-0.5 text-text-secondary text-[11px] font-ui font-semibold">
                @foreach($accessoryLabels as $label)<li>{{ $label }}</li>@endforeach
            </ul>
            @else
            <span class="text-[11px] text-text-muted font-ui">Tidak ada kelengkapan dicatat.</span>
            @endif
        </div>

        @if($conditionPhotoUrl)
        <div x-data class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs select-none">
            <div class="px-3.5 py-2 border-b border-border bg-surface-muted">
                <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Foto Kondisi Alat</span>
            </div>
            <div class="p-3.5">
                <button type="button" @click="$dispatch('open-image-preview', { url: '{{ $conditionPhotoUrl }}', label: 'Foto Kondisi Alat' })" class="group relative block w-40 rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                    <img src="{{ $conditionPhotoUrl }}" alt="Foto Kondisi Alat" class="h-full w-full object-cover">
                </button>
            </div>
        </div>
        @endif
    </div>
    @endif

    @if($retrieval->notes)
    <div class="bg-surface-muted border border-border rounded-xl p-4 shadow-xs min-w-0 max-w-full overflow-hidden">
        <span class="block text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui mb-2 select-none">
            {{ $outcome->isRetrieved() ? 'Catatan Teknisi' : 'Alasan Alat Tidak Diambil' }}
        </span>
        <p class="text-xs text-text-main leading-relaxed font-ui whitespace-pre-line break-words [word-break:break-word] min-w-0 font-medium">{{ $retrieval->notes }}</p>
    </div>
    @endif

    @unless($outcome->isRetrieved())
    <p class="text-[11px] text-text-muted font-ui select-none">
        Alat belum tercatat diambil — tombol "Ambil Alat" muncul lagi di List Putus Langganan untuk dijadwalkan ulang.
    </p>
    @endunless
</div>
@endif
