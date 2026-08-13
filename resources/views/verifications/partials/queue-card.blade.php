{{-- Kartu antrean untuk layar < lg. Tabel 9 kolom di lebar 390px cuma bisa
     dibaca dengan scroll horizontal dua arah — admin lapangan pegang HP, jadi
     baris dipecah jadi kartu: identitas di atas, meta berpasangan label/nilai,
     aksi di bawah dengan target sentuh 40px.
     Slot #customer-*-card-{id} adalah tempat JS menempel hasil refetch realtime
     (lihat refreshVerificationRow di queue.blade.php). --}}
@php
    $installation = $installation ?? $customer->latestInstallation;
@endphp
<article class="bg-surface border border-border rounded-lg p-4 space-y-3" id="customer-card-{{ $customer->id }}" data-pop-id="{{ $customer->pop_id }}" data-customer-id="{{ $customer->id }}">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <span class="font-mono text-xs font-semibold text-primary">{{ $customer->display_id }}</span>
            <h4 class="text-base font-semibold text-text-main truncate">{{ $customer->full_name }}</h4>
            <a href="tel:{{ $customer->primary_phone }}" class="font-mono text-xs text-text-muted hover:text-primary transition-colors">{{ $customer->primary_phone }}</a>
        </div>
        <div class="shrink-0" id="customer-status-card-{{ $customer->id }}">
            @include('verifications.partials.queue-status-badge', ['customer' => $customer])
        </div>
    </div>

    <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs border-t border-border pt-3">
        <div class="min-w-0">
            <dt class="text-[10px] font-semibold uppercase tracking-wider text-text-muted">Desa</dt>
            <dd class="text-text-main font-medium truncate">{{ $customer->village->name ?? '-' }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="text-[10px] font-semibold uppercase tracking-wider text-text-muted">Masuk Antrean</dt>
            <dd class="font-mono text-text-main">{{ $customer->created_at->format('d/m/y H:i') }}</dd>
        </div>
        <div class="col-span-2 min-w-0">
            <dt class="text-[10px] font-semibold uppercase tracking-wider text-text-muted">Waktu (Live)</dt>
            <dd id="customer-live-card-{{ $customer->id }}">
                @include('verifications.partials.queue-timer', ['customer' => $customer, 'installation' => $installation, 'idPrefix' => 'card-'])
            </dd>
        </div>
    </dl>

    <div class="border-t border-border pt-3" id="customer-action-card-{{ $customer->id }}">
        @include('verifications.partials.queue-actions', ['customer' => $customer, 'layout' => 'card'])
    </div>
</article>
