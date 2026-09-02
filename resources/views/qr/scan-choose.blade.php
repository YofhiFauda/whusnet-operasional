@extends('layouts.app')

@section('title', 'Pilih Aksi — Whusnet Operasional')
@section('page_title', 'Pilih Aksi')

@section('content')
{{--
    Chooser (2026-08-29) — dirender kalau staf eligible DUA-DUANYA
    tickets.qr.create & kolektor.qr.pay buat pelanggan ini. Sejak kolektor
    role dikasih tickets.qr.create juga (keputusan eksplisit user), ini JADI
    alur normal kolektor nagih: pelanggan komplain di tempat → kolektor
    pilih lapor tiket, bukan cuma kasus tepi akun full-access. Lihat
    docblock QrScanController §"Ambiguitas dua permission sekaligus".

    Desain (2026-08-31): momen ini dibuka staf DI DEPAN PINTU pelanggan,
    biasanya satu tangan pegang HP. Fokus halaman cuma dua hal: pastikan
    QR yang di-scan benar orangnya, lalu satu ketukan besar buat lanjut.
    Ikuti design-system/whusnet-operasional/Design.md — token warna/radius
    dipakai apa adanya lewat utility semantik (`bg-surface`, `text-text-muted`,
    dst) yang sudah otomatis ganti tema gelap/terang, TANPA prefix `dark:`
    manual (lihat resources/css/app.css §@theme).
--}}
<div class="mx-auto flex min-h-[65vh] max-w-md flex-col justify-center px-1 py-6">

    {{-- Kartu identitas hasil scan — bukan card, biar terasa seperti
         "kartu yang baru dipindai", bukan panel dashboard. --}}
    <div class="mb-7">
        <div class="mb-2.5 flex items-center gap-1.5">
            <span class="relative flex h-4 w-4 items-center justify-center">
                <span class="qr-scan-pulse absolute inline-flex h-full w-full rounded-full bg-success/40"></span>
                <svg class="relative h-4 w-4 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
            </span>
            <span class="text-[11px] font-semibold uppercase tracking-wider text-text-muted">Pelanggan Terverifikasi</span>
        </div>

        <h1 class="text-xl font-bold leading-snug text-text-main">{{ $customer->full_name }}</h1>

        <div class="mt-2 mb-3 flex flex-wrap items-center gap-1.5">
            <span class="inline-flex items-center rounded-full border border-border bg-surface-muted px-2.5 py-1 font-mono text-xs tracking-wide text-text-secondary">
                {{ $customer->display_id }}
            </span>
            @if($customer->pop)
                <span class="inline-flex items-center rounded-full border border-border bg-surface-muted px-2.5 py-1 text-xs text-text-secondary">
                    {{ $customer->pop->name }}
                </span>
            @endif
        </div>
    </div>

    <div class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-text-muted">
        Mau ngapain di sini?
    </div>

    <div class="space-y-2.5">
        <form method="POST" action="{{ route('qr.scan.choose.confirm', ['code' => $code]) }}">
            @csrf
            <input type="hidden" name="action" value="kolektor">
            <button type="submit"
                    class="group flex w-full items-center gap-3.5 rounded-lg border border-border bg-surface p-4 text-left transition-colors hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 active:scale-[0.98]">
                <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-success-bg text-success">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 8.25v10.5A1.5 1.5 0 003.75 20.25h16.5a1.5 1.5 0 001.5-1.5V8.25M2.25 8.25l1.72-3.44A1.5 1.5 0 015.32 4h13.36a1.5 1.5 0 011.35.81l1.72 3.44M12 13.5a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" />
                    </svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-text-main">Tagih Pembayaran</span>
                    <span class="block text-sm text-text-muted">Buka worklist tagihan pelanggan ini di Portal.</span>
                </span>
                <svg class="h-4 w-4 flex-shrink-0 text-border-strong transition-colors group-hover:text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </form>

        <form method="POST" action="{{ route('qr.scan.choose.confirm', ['code' => $code]) }}">
            @csrf
            <input type="hidden" name="action" value="tickets">
            <button type="submit"
                    class="group flex w-full items-center gap-3.5 rounded-lg border border-border bg-surface p-4 text-left transition-colors hover:border-primary focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 active:scale-[0.98]">
                <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-warning-bg text-warning">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-1a3 3 0 013-3h1.5a3 3 0 013 3v1M3 12a9 9 0 1118 0 9 9 0 01-18 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4.5M12 15.5h.008" />
                    </svg>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-text-main">Lapor Komplain</span>
                    <span class="block text-sm text-text-muted">Buat tiket keluhan pelanggan ini di Portal.</span>
                </span>
                <svg class="h-4 w-4 flex-shrink-0 text-border-strong transition-colors group-hover:text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </form>
    </div>

    <a href="{{ route('qr.scan.show') }}" class="mt-6 text-center text-xs font-medium text-text-muted hover:text-text-secondary">
        Bukan pelanggan ini? Pindai ulang QR
    </a>
</div>

<style>
    /* Pulsa sekali di ikon verifikasi — penegasan "QR ini valid", bukan
       dekorasi ambient terus-menerus. Dimatikan total buat yang minta
       reduced motion. */
    @keyframes qrScanPulseOnce {
        0% { transform: scale(0.6); opacity: 0.7; }
        100% { transform: scale(2.2); opacity: 0; }
    }
    .qr-scan-pulse {
        animation: qrScanPulseOnce 1.1s ease-out 1;
    }
    @media (prefers-reduced-motion: reduce) {
        .qr-scan-pulse { animation: none; display: none; }
    }
</style>
@endsection
