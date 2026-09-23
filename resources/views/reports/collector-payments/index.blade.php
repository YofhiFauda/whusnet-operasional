@extends('layouts.app')

@section('title', 'Laporan Bayar Kolektor - Whusnet Operasional')
@section('page_title', 'Laporan Bayar Kolektor')

@section('content')
@php
    $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
    $card = 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm';
    $collectorName = $collectorId ? optional($collectors->firstWhere('id', $collectorId))->name : null;
@endphp

<div class="space-y-6" x-data="{ q: '' }">
    {{-- Header + Kas Terkumpul --}}
    <div class="{{ $card }} p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-slate-100">Tabel Bayar Kolektor</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                Rekapitulasi pembayaran yang ditagih kolektor{{ $collectorName ? ' — '.$collectorName : '' }},
                {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}
            </p>
        </div>
        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800/50 px-4 py-2 rounded-lg text-emerald-700 dark:text-emerald-400 font-semibold text-sm">
            Kas Terkumpul: <span class="text-lg">{{ $rp($total) }}</span>
            <span class="block text-[11px] font-normal">{{ $count }} pembayaran (semua baris pada filter)</span>
        </div>
    </div>

    {{-- Filter --}}
    <div class="{{ $card }} p-4">
        <form method="GET" action="{{ route('reports.collector-payments.index') }}" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
            <div class="md:col-span-2">
                <label for="collector_id" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Kolektor</label>
                <select id="collector_id" name="collector_id" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm">
                    <option value="">Semua Kolektor</option>
                    @foreach($collectors as $collector)
                        <option value="{{ $collector->id }}" @selected((int) $collectorId === (int) $collector->id)>{{ $collector->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="start_date" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Dari</label>
                <input type="date" id="start_date" name="start_date" value="{{ $start_date }}" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm">
            </div>
            <div>
                <label for="end_date" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Sampai</label>
                <input type="date" id="end_date" name="end_date" value="{{ $end_date }}" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm">
            </div>
            <div>
                <label for="method" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Metode</label>
                <select id="method" name="method" class="w-full rounded-md border-slate-300 dark:border-slate-600 text-sm">
                    <option value="">Semua Metode</option>
                    @foreach($methods as $m)
                        <option value="{{ $m->value }}" @selected($method === $m->value)>{{ $m->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-md bg-sky-600 hover:bg-sky-700 text-white transition-colors">Tampilkan</button>
                @if($canExport)
                    <a href="{{ route('reports.collector-payments.export', array_filter(['collector_id' => $collectorId, 'start_date' => $start_date, 'end_date' => $end_date, 'method' => $method])) }}" class="px-4 py-2 text-sm font-semibold rounded-md border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Export</a>
                @endif
            </div>
        </form>
        @error('end_date')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- Pencarian di halaman (tidak mengubah Total Sub / Kas Terkumpul, yang dihitung server) --}}
    <div class="{{ $card }} p-4">
        <div class="relative w-full md:w-96">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" x-model="q" placeholder="Cari pelanggan, akun, alamat, atau keterangan..."
                   class="w-full pl-9 pr-3 py-2 rounded-lg border border-slate-300 dark:border-slate-600 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 bg-slate-50 dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
        </div>
    </div>

    {{-- Tabel --}}
    <div class="{{ $card }} overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead>
                    <tr class="bg-amber-100/70 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-800/40 text-slate-700 dark:text-slate-200 font-semibold uppercase text-xs tracking-wider">
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Kolektor</th>
                        <th class="py-3 px-4">Akun</th>
                        <th class="py-3 px-4">Nama Pelanggan</th>
                        <th class="py-3 px-4">Alamat</th>
                        <th class="py-3 px-4">Metode</th>
                        <th class="py-3 px-4 text-right">Jumlah</th>
                        <th class="py-3 px-4 text-right bg-amber-200/50 dark:bg-amber-800/30">Total Sub</th>
                        <th class="py-3 px-4">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700 text-slate-700 dark:text-slate-300">
                    @forelse($groups as $group)
                        @foreach($group['payments'] as $i => $payment)
                            @php
                                $isLast = $i === $group['payments']->count() - 1;
                                $customer = $payment->customer;
                                $haystack = strtolower(($customer?->full_name ?? '').' '.($customer?->cid ?? $customer?->customer_code ?? '').' '.($customer?->address ?? '').' '.$payment->note);
                            @endphp
                            <tr class="{{ $isLast ? 'bg-yellow-50/60 dark:bg-yellow-900/10' : '' }}" x-show="q === '' || @js($haystack).includes(q.toLowerCase())">
                                <td class="py-2.5 px-4 whitespace-nowrap text-slate-500">{{ $i === 0 ? $group['date']->format('d-m-y') : '' }}</td>
                                <td class="py-2.5 px-4 whitespace-nowrap">{{ $payment->collector?->name ?? '-' }}</td>
                                <td class="py-2.5 px-4 font-mono text-xs">{{ $customer?->cid ?? $customer?->customer_code ?? '-' }}</td>
                                <td class="py-2.5 px-4 font-medium text-slate-900 dark:text-slate-100">{{ $customer?->full_name ?? '-' }}</td>
                                <td class="py-2.5 px-4 text-slate-500">{{ \Illuminate\Support\Str::limit($customer?->address ?? '-', 40) }}</td>
                                <td class="py-2.5 px-4 whitespace-nowrap">{{ $payment->payment_method instanceof \App\Enums\PaymentMethod ? $payment->payment_method->label() : $payment->payment_method }}</td>
                                <td class="py-2.5 px-4 text-right font-mono">{{ $rp($payment->amount) }}</td>
                                <td class="py-2.5 px-4 text-right font-mono font-bold {{ $isLast ? 'bg-yellow-200/70 dark:bg-yellow-700/30 text-slate-900 dark:text-slate-100' : '' }}">{{ $isLast ? $rp($group['subtotal']) : '' }}</td>
                                <td class="py-2.5 px-4">
                                    @php $periodType = $payment->periodType(); @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $periodType->badgeClass() }}">{{ $periodType->label() }}</span>
                                    @if($payment->note)
                                        <span class="block mt-1 text-[11px] text-slate-500 dark:text-slate-400">{{ $payment->note }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="9" class="py-10 text-center text-slate-500">Tidak ada pembayaran kolektor pada filter ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
