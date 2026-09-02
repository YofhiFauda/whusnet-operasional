{{-- ID pelanggan di antrean = pemicu modal "Atur Jaringan & Mini POP", pola
     yang sama dengan kolom CID di List Pelanggan. Sengaja BUKAN tombol aksi
     tersendiri: baris antrean sudah padat aksi, dan ID-lah yang mewakili
     identitas jaringan pelanggan (CID dibentuk dari Mini POP + Distribusi).

     Dipakai dua tampilan (tabel desktop & kartu mobile) lewat prop $idClass —
     logika permission cuma boleh ada di sini, supaya ID tidak bisa diklik di
     satu tampilan dan mati di tampilan lain untuk user yang sama.

     Kedua URL dirender server-side lewat route(); JANGAN dirakit di JS dari id
     pelanggan (ADHOC-20 langkah 3). Status pra-pemasangan tetap ditolak
     CustomerNetworkAssignmentController — modal yang menjelaskan alasannya. --}}
@php
    $idClass = $idClass ?? 'font-mono text-xs font-semibold';
    $canAssignNetwork = auth()->user()->hasPermission('customers.detail.installation.validate');
@endphp
@if ($canAssignNetwork)
    <button type="button"
            onclick="openNetworkAssignmentModal('{{ route('customers.network-assignment.update', $customer) }}', '{{ route('customers.network-assignment.data', $customer) }}')"
            class="{{ $idClass }} text-primary hover:underline inline-flex items-center gap-1 text-left cursor-pointer"
            title="Klik untuk Atur Jaringan & Mini POP"
            aria-label="Atur jaringan dan Mini POP untuk {{ $customer->full_name }}">
        <span>{{ $customer->display_id }}</span>
        <svg class="w-3.5 h-3.5 shrink-0 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z" />
        </svg>
    </button>
@else
    <span class="{{ $idClass }} text-text-main">{{ $customer->display_id }}</span>
@endif
