@extends('layouts.app')

@section('title', 'Perangkat & Pemasangan - Whusnet Operasional')
@section('page_title', 'Perangkat & Pemasangan')

@section('content')
{{--
    Halaman terpisah dari Detail Pelanggan (customers.show) — teknisi gak
    punya customers.detail.view (Detail Pelanggan diblok buat teknisi), tapi
    tetap butuh isi/lihat data Perangkat & Pemasangan buat kerja lapangan.
    Reuse partial yang sama persis dengan tab Perangkat/Pemasangan di
    customers.show — lihat CustomerFieldworkController.
--}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h1 class="text-xl font-bold text-text-main tracking-tight">Perangkat & Pemasangan: {{ $customer->full_name }}</h1>
</div>

<div class="space-y-6">
    {{--
        Route guard di web.php pakai OR (devices.view|installation.view) —
        cukup salah satu buat MASUK halaman. Tapi visibility tiap tab harus
        tetap ngikut permission masing-masing, bukan ikut kebuka semua cuma
        gara-gara lolos gerbang OR itu. Makanya di-@can lagi di sini.
    --}}
    @if(auth()->user()->hasPermission('customers.detail.installation.view'))
    <div class="bg-surface border border-border rounded-lg p-6">
        @include('customers.tabs._installation')
    </div>
    @endif

    @if(auth()->user()->hasPermission('customers.detail.devices.view'))
    <div class="bg-surface border border-border rounded-lg p-6">
        @include('customers.tabs._device')
    </div>
    @endif
</div>

<script>
    function openInstallationModal() {
        const modal = document.getElementById('installation-modal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeInstallationModal() {
        const modal = document.getElementById('installation-modal');
        if (modal) modal.classList.add('hidden');
    }

    function openTestReportModal() {
        const modal = document.getElementById('test-report-modal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeTestReportModal() {
        const modal = document.getElementById('test-report-modal');
        if (modal) modal.classList.add('hidden');
    }

    function openDeviceModal() {
        const modal = document.getElementById('device-modal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeDeviceModal() {
        const modal = document.getElementById('device-modal');
        if (modal) modal.classList.add('hidden');
    }
</script>
@endsection
