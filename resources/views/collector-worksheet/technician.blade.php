@extends('layouts.app')
@section('title', 'Kas Teknisi - Whusnet Operasional')
@section('page_title', 'Kas Teknisi')
@section('content')
<div class="max-w-7xl mx-auto space-y-6 pb-16">
    <div>
        <a href="{{ route('collector-worksheet.index') }}" class="text-xs font-semibold text-amber-700 dark:text-amber-300">Kembali ke Worksheet Admin</a>
        <h1 class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">Kas Teknisi: {{ $technician->name }}</h1>
        <p class="text-sm text-slate-500">Pembayaran dan setoran yang dicatat atas nama teknisi ini.</p>
    </div>
    @if ($errors->any())
        <x-ui.alert variant="error" title="Aksi Ditolak">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</x-ui.alert>
    @endif
    @if (session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="rounded-2xl bg-slate-900 p-5 text-white">
            <div class="text-xs font-bold uppercase text-slate-300">Saldo Belum Disetor</div>
            <div class="mt-2 text-3xl font-bold font-mono">Rp {{ number_format($balance, 0, ',', '.') }}</div>
            <div class="text-xs text-slate-300">{{ $unsettledCount }} transaksi</div>
            @if (auth()->user()->hasPermission('collector_worksheet.deposit'))
                @if ($balance > 0)
                    <form method="POST" action="{{ route('collector-worksheet.technicians.deposit', $technician) }}" class="mt-4"
                          data-confirm="Setorkan seluruh saldo Rp {{ number_format($balance, 0, ',', '.') }} atas nama {{ $technician->name }}?">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Setor Seluruh Saldo</button>
                    </form>
                @else
                    <button type="button" disabled class="mt-4 rounded-xl bg-slate-700 px-4 py-2 text-sm font-semibold text-slate-300 cursor-not-allowed">Setor Seluruh Saldo</button>
                    <p class="mt-2 text-xs text-slate-300">Belum ada saldo yang dapat disetor.</p>
                @endif
            @else
                <p class="mt-3 text-xs text-slate-300">Anda tidak memiliki izin menyetor saldo Teknisi.</p>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5">
            <div class="text-xs font-bold uppercase text-slate-500">Kurang Setor</div>
            <div class="mt-2 text-3xl font-bold font-mono text-rose-700 dark:text-rose-300">Rp {{ number_format($outstandingShortfall, 0, ',', '.') }}</div>
        </div>
    </div>
    <nav class="flex gap-2 overflow-x-auto border-b border-slate-200 dark:border-slate-700" aria-label="Alur pembayaran Teknisi">
        @foreach ([
            'pembayaran' => 'Pembayaran',
            'sudah_bayar' => 'Sudah Dibayar',
            'setor' => 'Setor',
            'riwayat_pembayaran' => 'Riwayat Pembayaran',
        ] as $tabKey => $label)
            <a href="{{ route('collector-worksheet.technicians.show', ['technician' => $technician->id, 'tab' => $tabKey]) }}"
               @if ($tab === $tabKey) aria-current="page" @endif
               class="shrink-0 px-4 py-3 text-sm font-semibold border-b-2 {{ $tab === $tabKey ? 'border-amber-500 text-amber-700 dark:text-amber-300' : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                {{ $label }}@if ($tabKey === 'sudah_bayar') ({{ $unsettledCount }})@endif
            </a>
        @endforeach
    </nav>

    @if ($tab === 'pembayaran')
        @if (auth()->user()->hasPermission('payments.create'))
        <section class="space-y-4">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Catat Pembayaran atas Nama Teknisi</h2>
            <p class="text-sm text-slate-500">Cari pelanggan dalam POP teknisi. Pelanggan tidak perlu ditugaskan lebih dahulu.</p>
            <div id="batch-alert" class="hidden rounded-xl border p-4 text-sm"></div>
            <form method="GET" action="{{ route('collector-worksheet.technicians.show', $technician) }}" class="flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                <input type="hidden" name="tab" value="pembayaran">
                <input name="invoice_search" type="search" minlength="3" value="{{ $invoiceSearch }}" placeholder="Nama, CID, kode pelanggan, atau nomor tagihan" class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                <button type="submit" class="rounded-xl bg-sky-600 px-5 py-2 text-sm font-semibold text-white">Cari Tagihan</button>
            </form>
            @if (mb_strlen($invoiceSearch) >= 3)
                @include('partials.collector-pay-table', [
                    'invoices' => $invoices,
                    'canLogVisit' => false,
                    'emptyMessage' => 'Tidak ada tagihan pelanggan dalam POP teknisi yang cocok.',
                ])
                @push('scripts')
                    @include('partials.collector-pay-script', [
                        'storeUrl' => route('payment-batches.technicians.store', $technician),
                        'keyPrefix' => 'worksheet-teknisi-' . $technician->id,
                        'colspan' => 9,
                        'reloadOnSuccess' => true,
                        'emptyMessage' => 'Tidak ada tagihan pelanggan dalam POP teknisi yang cocok.',
                    ])
                @endpush
            @endif
        </section>
        @else
            <p class="rounded-2xl border border-slate-200 p-5 text-sm text-slate-500 dark:border-slate-700">Anda tidak memiliki izin mencatat pembayaran.</p>
        @endif
    @elseif ($tab === 'sudah_bayar')
    <section class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 overflow-hidden">
        <div class="p-5 border-b border-slate-200 dark:border-slate-700">
            <h2 class="font-bold text-slate-900 dark:text-white">Sudah Bayar, Belum Disetor</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm text-slate-700 dark:text-slate-200">
                <thead class="bg-slate-50 dark:bg-slate-900 text-xs uppercase text-slate-500"><tr><th class="p-3">Pelanggan</th><th class="p-3">No. Pembayaran</th><th class="p-3">Tanggal</th><th class="p-3">Metode</th><th class="p-3 text-right">Dibayar</th><th class="p-3 text-right">Untuk Disetor</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($unsettledPayments as $payment)
                        <tr>
                            <td class="p-3">{{ $payment->customer?->full_name ?? '-' }}</td>
                            <td class="p-3 font-mono">{{ $payment->payment_number }}</td>
                            <td class="p-3">{{ $payment->collected_date?->format('d/m/Y') ?? '-' }}</td>
                            <td class="p-3">{{ \App\Enums\PaymentMethod::tryFrom((string) $payment->payment_method)?->label() ?? $payment->payment_method }}</td>
                            <td class="p-3 text-right font-mono">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                            <td class="p-3 text-right font-mono font-semibold">Rp {{ number_format($payment->physicalAmount(), 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-slate-500">Tidak ada pembayaran yang belum disetor.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-200 dark:border-slate-700">{{ $unsettledPayments->links() }}</div>
    </section>
    @elseif ($tab === 'setor')
    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Setor Pembayaran Teknisi</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Setoran menunggu verifikasi admin dan riwayat perhitungan uang fisik.</p>
            </div>
        </div>
        @forelse ($deposits as $deposit)
            @php
                $computed = \App\Support\Money::sum($deposit->payments->map(fn ($payment) => $payment->physicalAmount()));
            @endphp
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 space-y-4">
                <div class="flex flex-wrap justify-between gap-3">
                    <div>
                        <div class="font-mono font-bold text-slate-900 dark:text-white">{{ $deposit->deposit_number }}</div>
                        <div class="text-xs text-slate-500">Disetor {{ $deposit->submitted_at?->format('d/m/Y H:i') ?? '-' }} - {{ $deposit->payments->count() }} pembayaran</div>
                    </div>
                    <span class="text-xs font-semibold text-amber-700 dark:text-amber-300">{{ $deposit->status->label() }}</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div><div class="text-slate-500">Nominal Tercatat</div><div class="font-mono font-bold">Rp {{ number_format($computed, 0, ',', '.') }}</div></div>
                    <div><div class="text-slate-500">Hitung Admin</div><div class="font-mono font-bold">{{ $deposit->declared_amount === null ? 'Belum dihitung' : 'Rp '.number_format((float) $deposit->declared_amount, 0, ',', '.') }}</div></div>
                    <div><div class="text-slate-500">Pelunasan Selisih</div><div class="font-mono font-bold">Rp {{ number_format((float) $deposit->settlement_amount, 0, ',', '.') }}</div></div>
                    <div><div class="text-slate-500">Sisa Kurang Setor</div><div class="font-mono font-bold">Rp {{ number_format($deposit->outstandingShortfall(), 0, ',', '.') }}</div></div>
                </div>
                @if ($deposit->note)<p class="text-xs text-slate-500">Catatan: {{ $deposit->note }}</p>@endif
                <details class="rounded-xl bg-slate-50 dark:bg-slate-900/60 p-3">
                    <summary class="cursor-pointer text-xs font-semibold">Lihat pembayaran dalam setoran</summary>
                    <div class="mt-3 space-y-2">
                        @foreach ($deposit->payments as $payment)
                            <div class="flex flex-wrap justify-between gap-2 border-t border-slate-200 dark:border-slate-700 pt-2 text-xs">
                                <div>{{ $payment->customer?->full_name ?? '-' }} <span class="font-mono text-slate-500">{{ $payment->payment_number }}</span>
                                    <span class="text-slate-500">{{ \App\Enums\PaymentMethod::tryFrom((string) $payment->payment_method)?->label() ?? $payment->payment_method }}</span>
                                </div>
                                <span class="font-mono">Rp {{ number_format($payment->physicalAmount(), 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                </details>
                @if ($deposit->status === \App\Enums\DepositStatus::MENUNGGU_VERIFIKASI && auth()->user()->hasPermission('collector_worksheet.validate'))
                    <form action="{{ route('collector-deposits.verify', $deposit->id) }}" method="POST" data-confirm="Verifikasi setoran {{ $deposit->deposit_number }} setelah uang fisik dihitung?" class="grid grid-cols-1 sm:grid-cols-3 gap-3 border-t border-slate-200 dark:border-slate-700 pt-4">
                        @csrf
                        <label class="text-xs text-slate-600 dark:text-slate-300">Uang Fisik Dihitung
                            <input name="declared_amount" type="text" required inputmode="decimal" data-rupiah value="{{ \App\Helpers\FormatHelper::rupiahInput($computed) }}" class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2">
                        </label>
                        @if ($openShortfallDeposits->isNotEmpty())
                            <label class="text-xs text-slate-600 dark:text-slate-300">Melunasi Setoran
                                <select name="settles_deposit_id" class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2">
                                    <option value="">Tidak ada</option>
                                    @foreach ($openShortfallDeposits as $open)
                                        <option value="{{ $open->id }}">{{ $open->deposit_number }} (sisa Rp {{ number_format($open->outstandingShortfall(), 0, ',', '.') }})</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="text-xs text-slate-600 dark:text-slate-300">Nominal Pelunasan
                                <input name="settlement_amount" type="text" inputmode="decimal" data-rupiah value="0" class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2">
                            </label>
                        @endif
                        <label class="text-xs text-slate-600 dark:text-slate-300">Catatan jika selisih
                            <input name="note" type="text" maxlength="1000" class="mt-1 w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2">
                        </label>
                        <button type="submit" class="self-end rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Verifikasi Setoran</button>
                    </form>
                @endif
                @if ($deposit->status === \App\Enums\DepositStatus::SELISIH && auth()->user()->hasPermission('collector_worksheet.approve'))
                    <form action="{{ route('collector-deposits.write-off', $deposit->id) }}" method="POST" data-confirm="Hapus buku sisa kurang setor {{ $deposit->deposit_number }}? Tindakan ini tidak dapat dibatalkan." class="border-t border-slate-200 dark:border-slate-700 pt-4 flex flex-wrap gap-2">
                        @csrf
                        <input name="write_off_reason" type="text" required maxlength="1000" placeholder="Alasan hapus buku" class="flex-1 min-w-52 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                        <button type="submit" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white">Hapus Buku</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-8 text-center text-slate-500">Belum ada setoran.</div>
        @endforelse
        {{ $deposits->links() }}
    </section>
    @else
    <section class="rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800 overflow-hidden">
        <div class="border-b border-slate-200 p-5 dark:border-slate-700">
            <h2 class="font-bold text-slate-900 dark:text-white">Riwayat Pembayaran Teknisi</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Seluruh pembayaran yang dicatat Teknisi ini, termasuk yang sudah disetor atau dikembalikan.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm text-slate-700 dark:text-slate-200">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-900">
                    <tr><th class="p-3">Tanggal</th><th class="p-3">Pelanggan / Tagihan</th><th class="p-3">Pembayaran</th><th class="p-3">Metode</th><th class="p-3 text-right">Nominal</th><th class="p-3">Status</th><th class="p-3">Setoran</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                    @forelse ($paymentHistory as $payment)
                        <tr>
                            <td class="p-3 whitespace-nowrap">{{ $payment->collected_date?->format('d/m/Y') ?? '-' }}</td>
                            <td class="p-3"><div class="font-semibold">{{ $payment->customer?->full_name ?? '-' }}</div><div class="font-mono text-xs text-slate-500">{{ $payment->invoice?->invoice_number ?? '-' }}</div></td>
                            <td class="p-3 font-mono text-xs">{{ $payment->payment_number }}</td>
                            <td class="p-3">{{ \App\Enums\PaymentMethod::tryFrom((string) $payment->payment_method)?->label() ?? $payment->payment_method }}</td>
                            <td class="p-3 text-right font-mono">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                            <td class="p-3">{{ $payment->payment_status->label() }}</td>
                            <td class="p-3">
                                @if ($payment->collectorDeposit)
                                    <div class="font-mono text-xs">{{ $payment->collectorDeposit->deposit_number }}</div>
                                    <div class="text-xs text-slate-500">{{ $payment->collectorDeposit->status->label() }}</div>
                                @else
                                    <span class="text-xs text-amber-700 dark:text-amber-300">Belum disetor</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-8 text-center text-slate-500">Belum ada riwayat pembayaran Teknisi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-4 dark:border-slate-700">{{ $paymentHistory->links() }}</div>
    </section>
    @endif
</div>
@endsection
