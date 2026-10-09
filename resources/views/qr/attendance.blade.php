@extends('layouts.app')

@section('title', 'Absen Task — Whusnet Operasional')
@section('page_title', 'Absen Task')

@section('content')
{{--
    Absen teknisi via QR (rancangan-qr-pelanggan-final.md §6.3). Satu ketukan:
    ambil GPS, lalu kirim. Kalau GPS ditolak/timeout, TETAP dikirim tanpa
    koordinat — server menandainya `tanpa_koordinat`, bukan menolak total,
    karena teknisi di lapangan tidak boleh terkunci oleh izin lokasi browser.
    Server yang memutuskan radius; nilai di form ini tidak dipercaya.
--}}
<div class="mx-auto flex min-h-[65vh] max-w-md flex-col justify-center px-1 py-6">

    <div class="mb-7">
        <div class="mb-2.5 text-[11px] font-semibold uppercase tracking-wider text-text-muted">Absen Task</div>

        <h1 class="text-xl font-bold leading-snug text-text-main">{{ $customer->full_name }}</h1>

        <div class="mt-2 mb-3 flex flex-wrap items-center gap-1.5">
            <span class="inline-flex items-center rounded-full border border-border bg-surface-muted px-2.5 py-1 font-mono text-xs tracking-wide text-text-secondary">
                {{ $task->task_number }}
            </span>
            <span class="inline-flex items-center rounded-full border border-border bg-surface-muted px-2.5 py-1 text-xs text-text-secondary">
                {{ $task->title }}
            </span>
        </div>
    </div>

    <form method="POST"
          action="{{ route('qr.attendance.store', ['code' => $code]) }}"
          id="attendance-form">
        @csrf
        <input type="hidden" name="latitude" id="attendance-latitude">
        <input type="hidden" name="longitude" id="attendance-longitude">
        <input type="hidden" name="accuracy" id="attendance-accuracy">

        <button type="submit" id="attendance-submit"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-primary p-4 font-semibold text-white transition-colors hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/30 active:scale-[0.98] disabled:opacity-60">
            <span id="attendance-label">Konfirmasi Kehadiran &amp; Mulai Task</span>
        </button>
    </form>

    <a href="{{ route('qr.scan.show') }}" class="mt-6 text-center text-xs font-medium text-text-muted hover:text-text-secondary">
        Batal
    </a>
</div>

<script>
    (function () {
        const form = document.getElementById('attendance-form');
        const submitButton = document.getElementById('attendance-submit');
        const label = document.getElementById('attendance-label');
        let submitting = false;

        form.addEventListener('submit', function (event) {
            if (submitting) {
                event.preventDefault();
                return;
            }

            // Tanpa Geolocation API (browser lama / HTTP biasa) langsung kirim
            // tanpa koordinat — server yang menandai hasilnya.
            if (!navigator.geolocation) {
                return;
            }

            event.preventDefault();
            submitting = true;
            submitButton.disabled = true;
            label.textContent = 'Mengambil lokasi…';

            const send = function () {
                form.submit();
            };

            navigator.geolocation.getCurrentPosition(function (position) {
                document.getElementById('attendance-latitude').value = position.coords.latitude;
                document.getElementById('attendance-longitude').value = position.coords.longitude;
                document.getElementById('attendance-accuracy').value = Math.round(position.coords.accuracy);
                send();
            }, function () {
                // Izin ditolak / timeout — kirim tanpa koordinat.
                send();
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
        });
    })();
</script>
@endsection
