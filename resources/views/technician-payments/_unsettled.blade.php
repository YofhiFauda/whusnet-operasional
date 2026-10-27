<section class="bg-white dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden shadow-xs" aria-label="Pembayaran belum disetor">
    <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700">
        <h2 class="text-base font-bold text-slate-900 dark:text-slate-100">Sudah Bayar, Belum Disetor</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Rincian pembayaran yang akan masuk saat Anda menekan Setor Seluruh Saldo. Nominal setor hanya menghitung uang yang Anda terima, tanpa saldo pelanggan.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-700 dark:text-slate-200">
            <thead class="bg-slate-50 dark:bg-slate-900/50 text-xs uppercase text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-5 py-3">Pelanggan</th>
                    <th class="px-5 py-3">No. Pembayaran</th>
                    <th class="px-5 py-3">Metode</th>
                    <th class="px-5 py-3">Tanggal</th>
                    <th class="px-5 py-3 text-right">Dibayar</th>
                    <th class="px-5 py-3 text-right">Untuk Disetor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse ($unsettledPayments as $payment)
                    <tr>
                        <td class="px-5 py-3">
                            <div class="font-semibold text-slate-900 dark:text-slate-100">{{ $payment->customer?->full_name ?? '-' }}</div>
                            <div class="text-xs text-slate-500">{{ $payment->customer?->cid ?? $payment->customer?->customer_code ?? '-' }}</div>
                        </td>
                        <td class="px-5 py-3 font-mono text-xs">{{ $payment->payment_number }}</td>
                        <td class="px-5 py-3 capitalize">{{ $payment->payment_method }}</td>
                        <td class="px-5 py-3">{{ $payment->collected_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-5 py-3 text-right font-mono">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                        <td class="px-5 py-3 text-right font-mono font-semibold text-emerald-700 dark:text-emerald-400">Rp {{ number_format($payment->physicalAmount(), 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">Belum ada pembayaran yang menunggu disetor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3 border-t border-slate-200 dark:border-slate-700">{{ $unsettledPayments->links() }}</div>
</section>
