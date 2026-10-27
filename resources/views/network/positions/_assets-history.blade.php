<section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-3">
    <h2 class="text-sm font-black uppercase tracking-wider text-text-main">Aset terpasang langsung</h2>
    <p class="text-xs text-text-muted">Hanya aset yang tercatat tepat di posisi ini — aset pada induk atau turunannya tidak dihitung di sini.</p>
    @forelse($directAssets as $serial)
        <a href="{{ route('warehouse.traceability.index', ['sn' => $serial->serial_number]) }}" class="block border-t border-border pt-2 text-sm hover:text-primary">
            <span class="font-semibold">{{ $serial->item?->name }}</span> · <span class="font-mono">SN {{ $serial->serial_number }}</span>
        </a>
    @empty
        <p class="text-xs text-text-muted">Belum ada aset terpasang.</p>
    @endforelse
</section>

<section class="rounded-2xl border border-border bg-surface p-6 shadow-sm space-y-3">
    <h2 class="text-sm font-black uppercase tracking-wider text-text-main">Riwayat task & ledger</h2>
    @forelse($actions as $action)
        <p class="border-t border-border pt-2 text-xs text-text-secondary">
            {{ $action->performed_at?->format('d M Y H:i') }} · {{ $action->action_type }} · {{ $action->technician?->name }}
            @if($action->result_note) — {{ $action->result_note }} @endif
        </p>
    @empty
        <p class="text-xs text-text-muted">Belum ada aksi laporan pada posisi ini.</p>
    @endforelse
    @foreach($history as $transaction)
        <p class="border-t border-border pt-2 text-xs text-text-secondary">
            <span class="font-mono">{{ $transaction->reference_number }}</span> · {{ $transaction->type->value }} · {{ $transaction->item?->name }}
            @if($transaction->serial) · SN {{ $transaction->serial->serial_number }} @endif
            · {{ $transaction->created_at?->format('d M Y H:i') }}
            @if($transaction->fopTask) · {{ $transaction->fopTask->task_number }} @endif
        </p>
    @endforeach
</section>
