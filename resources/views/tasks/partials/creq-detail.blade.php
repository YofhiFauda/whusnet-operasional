{{--
    Bagian khusus Laporan C-REQ (Jenis Permintaan, tikor, biaya & verifikasi) —
    dipakai Detail Task & Riwayat Task FOP. Field lain laporan C-REQ (kendala,
    material, SN, alat kerja, foto) SUDAH dirender blok Laporan Maintenance di
    masing-masing halaman, jadi partial ini sengaja cuma berisi yang khusus
    `task_creq_details` — supaya tidak ada dua salinan tampilan kendala/foto.

    docs/plan/task-teknisi/rancangan-biaya-creq-verifikasi-cs.md
--}}
@php
    $creq = $task->creqDetail;
    $creq?->loadMissing(['verifier', 'invoice']);
    $creqStatus = $creq?->verification_status;
    $creqStatusTone = match ($creqStatus) {
        \App\Enums\CReqVerificationStatus::VERIFIED => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800/60',
        \App\Enums\CReqVerificationStatus::REJECTED => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/50 dark:text-rose-300 dark:border-rose-800/60',
        default => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800/60',
    };
    $creqTikor = [
        'Tikor Lama' => [$creq?->tikor_lama_lat, $creq?->tikor_lama_lng],
        'Tikor Baru' => [$creq?->tikor_baru_lat, $creq?->tikor_baru_lng],
    ];
    $creqHasTikor = $creq && ($creq->tikor_lama_lat || $creq->tikor_baru_lat);
@endphp

@if($creq)
<div class="space-y-3 select-text">
    <div class="flex flex-wrap items-center justify-between gap-2 select-none">
        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Detail C-REQ</span>
        @if($creq->is_billable)
        <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border {{ $creqStatusTone }}">Berbayar · {{ $creqStatus->label() }}</span>
        @else
        <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border bg-surface-muted text-text-secondary border-border">Tidak Berbayar</span>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        <div class="bg-surface-muted border border-border p-3.5 rounded-xl shadow-xs">
            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Jenis Permintaan</span>
            <span class="font-bold text-text-main text-xs mt-1 block font-ui">{{ $creq->category->label() }}</span>
        </div>
        @if($creq->category_custom_name)
        <div class="bg-surface-muted border border-border p-3.5 rounded-xl shadow-xs">
            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Nama Kategori</span>
            <span class="font-bold text-text-main text-xs mt-1 block font-ui">{{ $creq->category_custom_name }}</span>
        </div>
        @endif
    </div>

    @if($creqHasTikor)
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
        @foreach($creqTikor as $tikorLabel => [$lat, $lng])
        <div class="bg-surface-muted border border-border p-3.5 rounded-xl shadow-xs">
            <div class="flex items-center justify-between select-none">
                <span class="text-[9px] text-text-muted font-bold uppercase font-ui">{{ $tikorLabel }}</span>
                @if($lat && $lng)
                <a href="https://www.google.com/maps/search/?api=1&query={{ $lat }},{{ $lng }}" target="_blank" rel="noopener" class="text-[10px] font-semibold text-sky-600 dark:text-sky-400 hover:underline">Maps ↗</a>
                @endif
            </div>
            <span class="font-mono text-xs font-semibold text-text-main mt-1 block">{{ $lat ?? '-' }}, {{ $lng ?? '-' }}</span>
        </div>
        @endforeach
    </div>
    @endif

    @if($creq->is_billable)
    <div class="bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/40 rounded-xl p-3.5 text-xs space-y-2 min-w-0">
        <span class="block text-[9px] text-amber-700 dark:text-amber-400 font-bold uppercase font-ui select-none">Catatan Biaya dari Teknisi</span>
        <p class="text-text-main font-medium whitespace-pre-line break-words [word-break:break-word]">{{ $creq->billing_note ?: '-' }}</p>

        <div class="pt-2 border-t border-amber-200/70 dark:border-amber-900/40 text-[11px] text-text-secondary font-ui space-y-1">
            @if($creq->verified_at)
            <p>{{ $creqStatus->label() }} oleh <b>{{ $creq->verifier?->name ?? '-' }}</b> · {{ \App\Support\IndonesianDate::dateTime($creq->verified_at) }}</p>
            @else
            <p>Menunggu verifikasi CS.</p>
            @endif

            @if($creqStatus === \App\Enums\CReqVerificationStatus::REJECTED && $creq->rejection_reason)
            <p class="text-rose-600 dark:text-rose-400">Alasan ditolak: {{ $creq->rejection_reason }}</p>
            @endif

            @if($creq->invoice)
            <p>
                Tagihan:
                @if(auth()->user()->hasPermission('invoices.view'))
                <a href="{{ route('invoices.show', $creq->invoice) }}" class="font-mono font-bold text-sky-600 dark:text-sky-400 hover:underline">{{ $creq->invoice->invoice_number }}</a>
                @else
                <span class="font-mono font-bold">{{ $creq->invoice->invoice_number }}</span>
                @endif
                · Rp {{ number_format((float) $creq->invoice->total_amount, 0, ',', '.') }}
            </p>
            @endif

            @if(auth()->user()->hasPermission('creq_billing_verification.view'))
            <a href="{{ route('tasks.creq-billing.show', $task) }}" class="inline-block font-semibold text-sky-600 dark:text-sky-400 hover:underline">Buka Verifikasi Biaya →</a>
            @endif
        </div>
    </div>
    @endif
</div>
@endif
