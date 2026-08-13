{{-- Tombol aksi per pelanggan di antrean verifikasi.
     Dipakai dua bentuk lewat prop $layout:
       'row'  → baris tabel desktop (ikon rapat, tombol tinggi 32px)
       'card' → kartu mobile (target sentuh 40px, tombol utama full-width)
     Logika permission & tujuan link SENGAJA cuma ada di sini. Menyalinnya ke
     markup mobile berarti tombol "Verifikasi Admin" bisa muncul di satu
     tampilan dan hilang di tampilan lain untuk user yang sama. --}}
@php
    $layout = $layout ?? 'row';
    $isCard = $layout === 'card';

    $canValidate = auth()->user()->hasPermission('customers.detail.installation.validate') || auth()->user()->hasFullAccess();
    $detailUrl = match (true) {
        $canValidate => route('customers.verification.admin', $customer),
        in_array($customer->status, ['installation_in_progress', 'revision_installation', 'installed', 'verification_admin']) => route('customers.installation.report', ['customer' => $customer, 'return_to' => route('verifications.queue')]),
        in_array($customer->status, ['waiting_acc', 'surveyed', 'waiting_installation']) => route('customers.survey.report', ['customer' => $customer, 'return_to' => route('verifications.queue')]),
        auth()->user()->hasPermission('customers.detail.view') => route('customers.show', $customer),
        default => route('customers.fieldwork', $customer),
    };

    $btn = 'inline-flex items-center justify-center gap-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider text-white shadow-sm transition-colors cursor-pointer '
        .($isCard ? 'h-10 px-4 flex-1' : 'h-8 px-3');
    $iconBtn = 'inline-flex items-center justify-center rounded-lg text-text-muted transition-colors '
        .($isCard ? 'h-10 w-10 border border-border bg-surface' : 'h-8 w-8');
    $iconSize = $isCard ? 'w-5 h-5' : 'w-[18px] h-[18px]';
@endphp
<div class="flex items-center gap-2 {{ $isCard ? 'w-full' : 'justify-end' }}">
    <button type="button" class="{{ $iconBtn }} hover:text-primary hover:border-primary" title="Generate/Lihat QR" aria-label="Generate atau lihat QR pelanggan" onclick="window.Toast.info('Mockup', 'Generate/Lihat QR')">
        <svg class="{{ $iconSize }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
        </svg>
    </button>

    @if ($customer->status === 'waiting_acc' || $customer->status === 'surveyed')
        @if ($canValidate)
            <a href="{{ route('customers.verification.admin', $customer) }}" class="{{ $btn }} bg-warning hover:bg-warning/90">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                Detail &amp; Review
            </a>
        @else
            <a href="{{ $detailUrl }}" class="{{ $isCard ? $btn.' bg-primary hover:bg-primary/90' : $iconBtn.' hover:text-primary' }}" title="Detail" aria-label="Lihat detail pelanggan">
                <svg class="{{ $isCard ? 'w-4 h-4 shrink-0' : $iconSize }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                @if ($isCard) Detail @endif
            </a>
        @endif
    @elseif ($customer->status === 'installed' || $customer->status === 'verification_admin')
        @if ($canValidate)
            <a href="{{ route('customers.verification.admin', $customer) }}" class="{{ $btn }} bg-success hover:bg-success/90">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Verifikasi Admin
            </a>
        @else
            <a href="{{ $detailUrl }}" class="{{ $isCard ? $btn.' bg-primary hover:bg-primary/90' : $iconBtn.' hover:text-primary' }}" title="Detail" aria-label="Lihat detail pelanggan">
                <svg class="{{ $isCard ? 'w-4 h-4 shrink-0' : $iconSize }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                @if ($isCard) Detail @endif
            </a>
        @endif
    @else
        <a href="{{ $detailUrl }}" class="{{ $iconBtn }} hover:text-primary hover:border-primary" title="Detail" aria-label="Lihat detail pelanggan">
            <svg class="{{ $iconSize }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
        </a>
        @can('customers.detail.installation.validate')
            {{-- Target POST dirender SERVER-SIDE (route()), bukan dirakit di JS dari
                 id pelanggan. Dulu queue.blade.php menyusun `/verifications/${id}/reject`
                 sebagai string literal — path route diduplikasi di JS, dan kalau
                 skripnya gagal form tetap punya action kosong (= POST ke URL halaman
                 antrean). ADHOC-20 langkah 3. --}}
            <button type="button" onclick="openRejectModal('{{ route('customers.verification.reject', $customer) }}')" class="{{ $iconBtn }} hover:text-error hover:border-error" title="Batal / Gagal" aria-label="Batalkan atau tandai gagal">
                <svg class="{{ $iconSize }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        @endcan

        @if ($customer->status === 'waiting_installation')
            <form action="{{ route('customers.installation.start', $customer) }}" method="POST" class="m-0 p-0 {{ $isCard ? 'flex-1 flex' : 'inline-block' }}" onsubmit="event.preventDefault(); window.confirmAction('Mulai proses pemasangan untuk pelanggan ini?', this);">
                @csrf
                <button type="submit" class="{{ $btn }} bg-primary hover:bg-primary/90">Start Proses</button>
            </form>
        @elseif ($customer->status === 'installation_in_progress')
            <a href="{{ route('customers.installation.report', ['customer' => $customer, 'return_to' => route('verifications.queue')]) }}" class="{{ $btn }} bg-success hover:bg-success/90">
                Lapor Pemasangan
            </a>
        @elseif ($customer->status === 'revision_installation')
            <a href="{{ route('customers.installation.report', ['customer' => $customer, 'return_to' => route('verifications.queue')]) }}" class="{{ $btn }} bg-error hover:bg-error/90">
                Revisi
            </a>
        @endif
    @endif
</div>
