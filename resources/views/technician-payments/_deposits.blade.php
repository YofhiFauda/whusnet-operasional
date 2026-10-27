<section class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden">
    <div class="p-5 border-b border-slate-200 dark:border-slate-700">
        <h2 class="font-bold text-slate-900 dark:text-white">Riwayat Setoran Saya</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400">Nominal pembayaran yang masuk ke setoran dan hasil verifikasi admin.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-700 dark:text-slate-200">
            <thead class="bg-slate-50 dark:bg-slate-900 text-xs uppercase">
                <tr><th class="p-3">No. Setoran</th><th class="p-3">Tanggal</th><th class="p-3">Jumlah</th><th class="p-3">Nominal Disetor</th><th class="p-3">Hitung Admin</th><th class="p-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse ($deposits as $deposit)
                    @php $amount = \App\Support\Money::sum($deposit->payments->map(fn ($payment) => $payment->physicalAmount())); @endphp
                    <tr>
                        <td class="p-3 font-mono">{{ $deposit->deposit_number }}</td>
                        <td class="p-3">{{ $deposit->submitted_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td class="p-3">{{ $deposit->payments->count() }} transaksi</td>
                        <td class="p-3 font-mono font-semibold">Rp {{ number_format($amount, 0, ',', '.') }}</td>
                        <td class="p-3 font-mono">{{ $deposit->declared_amount !== null ? 'Rp '.number_format((float) $deposit->declared_amount, 0, ',', '.') : 'Belum dihitung' }}</td>
                        <td class="p-3">
                            <span class="font-semibold">{{ $deposit->status->label() }}</span>
                            @if ($deposit->outstandingShortfall() > 0)
                                <div class="text-xs text-rose-700 dark:text-rose-300">Kurang Rp {{ number_format($deposit->outstandingShortfall(), 0, ',', '.') }}</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td colspan="6" class="px-3 pb-3">
                            <details class="rounded-xl bg-slate-50 dark:bg-slate-900/60 p-3">
                                <summary class="cursor-pointer text-xs font-semibold text-amber-700 dark:text-amber-300">Lihat {{ $deposit->payments->count() }} pembayaran dalam setoran</summary>
                                <div class="mt-3 space-y-2">
                                    @foreach ($deposit->payments as $payment)
                                        <div class="flex flex-wrap justify-between gap-2 border-t border-slate-200 dark:border-slate-700 pt-2 text-xs">
                                            <div>
                                                <span class="font-semibold">{{ $payment->customer?->full_name ?? '-' }}</span>
                                                <span class="font-mono text-slate-500">{{ $payment->payment_number }}</span>
                                            </div>
                                            <span class="font-mono">Rp {{ number_format($payment->physicalAmount(), 0, ',', '.') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-8 text-center text-slate-500">Belum ada setoran.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4 border-t border-slate-200 dark:border-slate-700">{{ $deposits->links() }}</div>
</section>
