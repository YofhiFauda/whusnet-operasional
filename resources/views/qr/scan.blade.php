@extends('layouts.app')

@section('title', 'Scan QR — Whusnet Operasional')
@section('page_title', 'Scan QR')

@section('content')
{{--
    Scan QR Internal (2026-08-27) — WAJIB dipakai staf, GANTIKAN asumsi
    scan pakai app kamera/scanner pihak ketiga. Lihat docblock
    QrInAppScanController + resources/js/qr-scan.js kenapa: cabang staf di
    QrScanController::dispatch() gantung cookie sesi, app luar gak bawa itu.
--}}
<div class="max-w-md mx-auto">
    <p class="text-sm text-text-secondary mb-4">
        Arahkan kamera ke QR pelanggan. Halaman otomatis pindah begitu QR
        valid kebaca — gak perlu tombol apa pun.
    </p>

    <div class="relative bg-black rounded-xl overflow-hidden aspect-square">
        <video id="qr-scan-video" class="w-full h-full object-cover" playsinline muted></video>
        <canvas id="qr-scan-canvas" class="hidden"></canvas>

        {{-- Bingkai target — murni visual, gak mempengaruhi area decode
             (decode-nya baca FRAME PENUH, bukan cuma area bingkai). --}}
        <div class="absolute inset-8 border-2 border-white/70 rounded-lg pointer-events-none"></div>
    </div>

    <p id="qr-scan-status" class="mt-4 text-center text-sm text-text-muted min-h-[1.5em]">
        Meminta izin kamera…
    </p>
</div>

@vite(['resources/js/qr-scan.js'])
@endsection
