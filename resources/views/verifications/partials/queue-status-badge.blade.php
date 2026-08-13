{{-- Badge status antrean. Dipisah supaya tampilan tabel (desktop) dan kartu
     (mobile) memakai SATU sumber label+warna — kalau digandakan, tiap
     penambahan status baru harus diingat di dua tempat dan pasti menyimpang. --}}
@php
    $statusLabel = match ($customer->status) {
        'waiting_acc', 'surveyed' => 'MENUNGGU ACC',
        'waiting_installation' => 'MENUNGGU PEMASANGAN',
        'installation_in_progress' => 'MULAI PASANG',
        'revision_installation' => 'REVISI PEMASANGAN',
        'installed', 'verification_admin' => 'VERIFIKASI ADMIN',
        default => $customer->status,
    };

    $statusStyle = match ($customer->status) {
        'waiting_acc', 'surveyed' => 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border);',
        'waiting_installation' => 'background:var(--color-surface-muted); color:var(--color-text-main); border-color:var(--color-border);',
        'installation_in_progress' => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border);',
        'revision_installation' => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border);',
        'installed', 'verification_admin' => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border);',
        default => 'background:var(--color-surface-muted); color:var(--color-text-main); border-color:var(--color-border);',
    };

    $showPulse = in_array($customer->status, ['installation_in_progress', 'revision_installation'], true);
@endphp
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] sm:text-[11px] font-bold tracking-wide uppercase border whitespace-nowrap" style="{{ $statusStyle }}">
    @if ($showPulse)
        <span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background:currentColor"></span>
    @endif
    {{ $statusLabel }}
</span>
