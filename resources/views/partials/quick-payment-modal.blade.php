{{-- Modal Bayar Cepat: input pembayaran langsung dari baris invoice manapun,
     tanpa pindah halaman. Dipakai di customers/show.blade.php (tab Tagihan)
     dan invoices/index.blade.php (list global). --}}
@can('payments.create')
    <div id="quick-payment-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-lg shadow-xl border border-slate-200 w-full max-w-md overflow-hidden transform transition-all">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                    Bayar Cepat — <span id="qp-invoice-number" class="font-mono text-sky-700"></span>
                </h3>
                <button type="button" onclick="closeQuickPaymentModal()" class="text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <form id="quick-payment-form">
                <div class="p-6 space-y-4">
                    <div id="qp-error" class="hidden text-xs text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2"></div>

                    <div class="p-3 bg-slate-50 border border-slate-100 rounded-lg text-xs flex justify-between">
                        <span class="text-slate-500">Sisa Tagihan:</span>
                        <span class="font-mono font-bold text-slate-900" id="qp-remaining">Rp 0</span>
                    </div>

                    <div>
                        <label for="qp-amount" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Nominal Bayar</label>
                        <input type="number" id="qp-amount" min="1" step="1" required
                               class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                    </div>

                    <div>
                        <label for="qp-payment-date" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Tanggal Bayar</label>
                        <input type="date" id="qp-payment-date" required
                               class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-mono">
                    </div>

                    <div>
                        <label for="qp-payment-method" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Metode</label>
                        <select id="qp-payment-method" required
                                class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs font-semibold">
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer</option>
                            <option value="qris">QRIS</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label for="qp-note" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Catatan (Opsional)</label>
                        <input type="text" id="qp-note" maxlength="1000"
                               class="w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm focus:ring-sky-500 focus:border-sky-500 text-xs">
                    </div>
                </div>
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex justify-end gap-2 text-xs">
                    <button type="button" onclick="closeQuickPaymentModal()" class="px-3 py-1.5 border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                        Batal
                    </button>
                    <button type="submit" id="qp-submit-btn" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-md shadow-sm transition-colors cursor-pointer">
                        Catat Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            let qpInvoiceId = null;

            function openQuickPaymentModal(invoiceId, invoiceNumber, remainingAmount) {
                qpInvoiceId = invoiceId;
                document.getElementById('qp-invoice-number').textContent = invoiceNumber;
                document.getElementById('qp-remaining').textContent = 'Rp ' + Math.round(remainingAmount).toLocaleString('id-ID');
                document.getElementById('qp-amount').value = Math.round(remainingAmount);
                document.getElementById('qp-amount').max = remainingAmount;
                document.getElementById('qp-payment-date').value = new Date().toISOString().slice(0, 10);
                document.getElementById('qp-payment-method').value = 'cash';
                document.getElementById('qp-note').value = '';
                document.getElementById('qp-error').classList.add('hidden');
                document.getElementById('quick-payment-modal').classList.remove('hidden');
            }

            function closeQuickPaymentModal() {
                document.getElementById('quick-payment-modal').classList.add('hidden');
                qpInvoiceId = null;
            }

            document.getElementById('quick-payment-form')?.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!qpInvoiceId) return;

                const submitBtn = document.getElementById('qp-submit-btn');
                const errorBox = document.getElementById('qp-error');
                errorBox.classList.add('hidden');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Menyimpan...';

                const payload = new FormData();
                payload.append('amount', document.getElementById('qp-amount').value);
                payload.append('payment_date', document.getElementById('qp-payment-date').value);
                payload.append('payment_method', document.getElementById('qp-payment-method').value);
                payload.append('note', document.getElementById('qp-note').value);

                fetch(`/invoices/${qpInvoiceId}/payments`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: payload,
                })
                    .then(async (res) => {
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || !data.success) {
                            throw new Error(data.message || 'Gagal mencatat pembayaran.');
                        }
                        return data;
                    })
                    .then((data) => {
                        const badge = document.getElementById('invoice-status-badge-' + data.invoice.id);
                        if (badge) {
                            const statusMap = {
                                lunas: ['Lunas', 'bg-green-50 text-green-700 border-green-200'],
                                sebagian: ['Sebagian', 'bg-blue-50 text-blue-700 border-blue-200'],
                                belum_dibayar: ['Belum Dibayar', 'bg-amber-50 text-amber-700 border-amber-200'],
                            };
                            const [label, cls] = statusMap[data.invoice.invoice_status] || ['-', 'bg-slate-50 text-slate-700 border-slate-200'];
                            const baseClass = badge.dataset.badgeStyle === 'tag'
                                ? 'inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border uppercase tracking-wide'
                                : 'px-2 py-0.5 text-[10px] font-bold rounded-full border';
                            badge.className = baseClass + ' ' + cls;
                            badge.dataset.badgeStyle = badge.dataset.badgeStyle; // preserve marker after className overwrite
                            badge.textContent = label;
                        }
                        const row = document.getElementById('invoice-row-' + data.invoice.id);
                        if (row && ['lunas', 'batal'].includes(data.invoice.invoice_status)) {
                            const payButton = row.querySelector('button[onclick^="openQuickPaymentModal"]');
                            if (payButton) payButton.remove();
                        }
                        closeQuickPaymentModal();
                        alert(data.message);
                    })
                    .catch((err) => {
                        errorBox.textContent = err.message;
                        errorBox.classList.remove('hidden');
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Catat Pembayaran';
                    });
            });
        </script>
    @endpush
@endcan
