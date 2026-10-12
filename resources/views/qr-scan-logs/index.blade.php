@extends('layouts.app')

@section('title', 'Riwayat Scan QR')

@section('content')
<div class="mx-auto max-w-7xl space-y-5 px-4 py-6">
    <div>
        <h1 class="text-xl font-bold text-text-main font-ui">Riwayat Scan QR</h1>
        <p class="mt-1 text-sm text-text-muted font-ui">Riwayat percobaan scan QR, termasuk yang gagal, pada POP yang dapat Anda akses.</p>
    </div>

    <form method="GET" action="{{ route('qr-scan-logs.index') }}" class="grid gap-3 rounded-xl border border-border bg-surface p-4 shadow-xs sm:grid-cols-2 lg:grid-cols-5">
        <label class="text-xs font-semibold text-text-secondary font-ui">
            Periode
            <select name="preset" class="mt-1 block w-full rounded-lg border border-border bg-surface-muted px-3 py-2 text-text-main">
                @foreach(['today' => 'Hari Ini', 'yesterday' => 'Kemarin', 'last_7_days' => '7 Hari Terakhir', 'this_month' => 'Bulan Ini', 'custom' => 'Tanggal Khusus', 'all' => 'Semua Waktu'] as $value => $label)
                    <option value="{{ $value }}" @selected($preset === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-text-secondary font-ui">
            Dari
            <input type="date" name="date_from" value="{{ $dateFrom }}" class="mt-1 block w-full rounded-lg border border-border bg-surface-muted px-3 py-2 text-text-main">
        </label>
        <label class="text-xs font-semibold text-text-secondary font-ui">
            Sampai
            <input type="date" name="date_to" value="{{ $dateTo }}" class="mt-1 block w-full rounded-lg border border-border bg-surface-muted px-3 py-2 text-text-main">
        </label>
        <label class="text-xs font-semibold text-text-secondary font-ui">
            Teknisi
            <select name="user_id" class="mt-1 block w-full rounded-lg border border-border bg-surface-muted px-3 py-2 text-text-main">
                <option value="">Semua teknisi</option>
                @foreach($technicians as $technician)
                    <option value="{{ $technician->id }}" @selected((string) $userFilter === (string) $technician->id)>{{ $technician->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-text-secondary font-ui">
            Hasil
            <select name="result" class="mt-1 block w-full rounded-lg border border-border bg-surface-muted px-3 py-2 text-text-main">
                <option value="">Semua hasil</option>
                @foreach($resultOptions as $result)
                    <option value="{{ $result }}" @selected($resultFilter === $result)>{{ $result }}</option>
                @endforeach
            </select>
        </label>
        <div class="flex items-center gap-3 sm:col-span-2 lg:col-span-5">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-xs font-bold text-white hover:bg-primary-hover">Terapkan</button>
            <a href="{{ route('qr-scan-logs.index') }}" class="text-xs font-semibold text-text-muted hover:text-text-main">Reset</a>
            <span class="ml-auto text-xs text-text-muted">{{ $periodLabel }} · {{ $logs->total() }} scan</span>
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border border-border bg-surface shadow-xs">
        <table class="w-full min-w-[900px] text-left text-xs font-ui">
            <thead class="border-b border-border bg-surface-muted text-text-muted">
                <tr>
                    <th class="px-4 py-3">Waktu</th>
                    <th class="px-4 py-3">Teknisi</th>
                    <th class="px-4 py-3">POP</th>
                    <th class="px-4 py-3">Pelanggan</th>
                    <th class="px-4 py-3">Tujuan</th>
                    <th class="px-4 py-3">Hasil</th>
                    <th class="px-4 py-3">Alasan</th>
                    <th class="px-4 py-3">Jarak</th>
                    <th class="px-4 py-3">Lokasi Scan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border text-text-secondary">
                @forelse($logs as $log)
                    <tr class="hover:bg-surface-muted/50">
                        <td class="whitespace-nowrap px-4 py-3">{{ $log->scanned_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $log->resolved_pop_name }}</td>
                        <td class="px-4 py-3">{{ $log->customer?->full_name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $log->purpose ?? '—' }}</td>
                        <td class="px-4 py-3"><span class="rounded border px-2 py-0.5 font-semibold {{ $log->result === 'success' ? 'border-success-border bg-success-bg text-success' : 'border-error-border bg-error-bg text-error' }}">{{ $log->result }}</span></td>
                        <td class="px-4 py-3">{{ $log->reason ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3">{{ $log->distance_meters !== null ? number_format($log->distance_meters, 0, ',', '.').' m' : '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3">@if($log->latitude !== null && $log->longitude !== null)<a href="https://www.google.com/maps?q={{ $log->latitude }},{{ $log->longitude }}" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">Lihat lokasi</a>@else—@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-12 text-center text-text-muted">Tidak ada scan QR pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</div>
@endsection