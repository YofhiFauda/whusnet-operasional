@extends('layouts.app')

@section('title', 'Dashboard Delivery Push Notification')
@section('page_title', 'Dashboard Delivery Push Notification')

@section('content')
<div class="space-y-5">
    <div>
        <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">Delivery Rate per Merek HP</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            Perkiraan, bukan angka presisi per device. "Terkirim" = push notifikasi dikirim ke seseorang,
            "Diterima" = service worker berhasil ack. Kalau satu orang punya lebih dari satu HP beda merek,
            satu notifikasi yang sama dihitung ke SEMUA merek HP yang dia punya saat ini — bukan device
            spesifik mana yang beneran ack. Pakai angka ini sebagai sinyal kasar "merek apa yang perlu
            diingatkan onboarding ulang", bukan laporan presisi.
        </p>
    </div>

    {{-- Filter periode — GET biasa, cukup link (tanpa mutasi data). --}}
    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="text-slate-500 dark:text-slate-400">Periode:</span>
        @foreach($periods as $period)
            <a href="{{ route('webpush.dashboard', ['days' => $period]) }}"
               class="rounded-lg border px-3 py-1.5 font-semibold {{ $days === $period ? 'border-sky-300 bg-sky-50 text-sky-700 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                {{ $period }} hari terakhir
            </a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800">
                <tr>
                    <th class="px-4 py-3">Merek / OS</th>
                    <th class="px-4 py-3 text-right">Terkirim</th>
                    <th class="px-4 py-3 text-right">Diterima</th>
                    <th class="px-4 py-3 text-right">Eskalasi</th>
                    <th class="px-4 py-3 text-right">Delivery Rate</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($rows as $row)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $row['brand'] }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['sent'] }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['delivered'] }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ $row['escalated'] }}</td>
                        <td class="px-4 py-3 text-right">
                            @if($row['delivery_rate'] < 50)
                                <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-950/50 dark:text-rose-400">{{ $row['delivery_rate'] }}%</span>
                            @elseif($row['delivery_rate'] < 80)
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950/50 dark:text-amber-400">{{ $row['delivery_rate'] }}%</span>
                            @else
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400">{{ $row['delivery_rate'] }}%</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada data pengiriman push notification.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
