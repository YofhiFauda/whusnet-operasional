{{-- Modal Bayar Cepat: input pembayaran langsung dari baris invoice manapun,
     tanpa pindah halaman. Dipakai di customers/show.blade.php (tab Tagihan)
     dan invoices/index.blade.php (list global). --}}
@can('payments.create')
    <div id="quick-payment-modal" class="fixed inset-0 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-[2px] transition-opacity z-50 flex items-center justify-center p-4 hidden">
        <form id="quick-payment-form" class="bg-white dark:bg-slate-900 rounded-xl shadow-2xl border border-slate-200/80 dark:border-slate-800 w-full max-w-3xl overflow-hidden transform transition-all flex flex-col md:grid md:grid-cols-2">
            
            <!-- Modal Header (Spans both columns) -->
            <div class="col-span-2 px-6 py-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-white dark:bg-slate-900">
                <h3 class="text-xs font-bold text-slate-800 dark:text-slate-100 uppercase tracking-wider flex items-center gap-2">
                    <span>Catat Pembayaran Cepat</span>
                    <span class="text-slate-300 dark:text-slate-700 font-normal">|</span>
                    <span id="qp-invoice-number" class="font-mono text-sky-700 dark:text-sky-400 font-bold"></span>
                </h3>
                <button type="button" onclick="closeQuickPaymentModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 focus:outline-none cursor-pointer p-1 rounded-md hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Left Column: Ringkasan & Rincian Biaya -->
            <div class="p-6 bg-slate-50/40 dark:bg-slate-800/30 border-r border-slate-100 dark:border-slate-800 flex flex-col justify-between">
                <div>
                    <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-3">Ringkasan Tagihan</h4>
                    <div class="space-y-2.5 text-xs text-slate-600 dark:text-slate-300">
                        <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100/50 dark:border-slate-800/60">
                            <span class="text-slate-400 dark:text-slate-500">Pelanggan</span>
                            <span id="qp-pelanggan" class="font-semibold text-slate-800 dark:text-slate-200 text-right">-</span>
                        </div>
                        <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100/50 dark:border-slate-800/60">
                            <span class="text-slate-400 dark:text-slate-500">Alamat</span>
                            <span id="qp-alamat" class="font-medium text-slate-800 dark:text-slate-200 text-right max-w-[200px] break-words">-</span>
                        </div>
                        <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100/50 dark:border-slate-800/60">
                            <span class="text-slate-400 dark:text-slate-500">Paket Internet</span>
                            <span id="qp-paket" class="font-semibold text-slate-800 dark:text-slate-200 text-right">-</span>
                        </div>
                        <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100/50 dark:border-slate-800/60">
                            <span class="text-slate-400 dark:text-slate-500">POP / Cabang</span>
                            <span id="qp-pop" class="font-medium text-slate-800 dark:text-slate-200 text-right">-</span>
                        </div>
                        <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100/50 dark:border-slate-800/60">
                            <span class="text-slate-400 dark:text-slate-500">No. Tagihan</span>
                            <span id="qp-no-tagihan-details" class="font-mono font-bold text-sky-700 dark:text-sky-400">-</span>
                        </div>
                        <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100/50 dark:border-slate-800/60">
                            <span class="text-slate-400 dark:text-slate-500">Periode</span>
                            <span id="qp-periode" class="font-mono text-slate-800 dark:text-slate-200 text-right">-</span>
                        </div>
                        <div class="flex justify-between gap-4 py-1.5 border-b border-slate-100/50 dark:border-slate-800/60">
                            <span class="text-slate-400 dark:text-slate-500">Jatuh Tempo</span>
                            <span id="qp-jatuh-tempo" class="font-mono text-slate-800 dark:text-slate-200 text-right">-</span>
                        </div>
                    </div>

                    <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mt-5 mb-3">Rincian Biaya</h4>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between gap-4">
                            <span class="text-slate-500 dark:text-slate-400">Harga Paket (Subtotal)</span>
                            <span id="qp-subtotal" class="font-mono text-slate-800 dark:text-slate-200">Rp 0</span>
                        </div>
                        <div class="flex justify-between gap-4 text-green-700 dark:text-emerald-400 hidden" id="qp-discount-row">
                            <span>Potongan Diskon</span>
                            <span id="qp-discount" class="font-mono">- Rp 0</span>
                        </div>
                        <div class="flex justify-between gap-4">
                            <span class="text-slate-500 dark:text-slate-400" id="qp-ppn-label">PPN</span>
                            <span id="qp-ppn" class="font-mono text-slate-800 dark:text-slate-200">Rp 0</span>
                        </div>
                        <div class="flex justify-between gap-4 hidden" id="qp-prorate-row">
                            <span class="text-slate-500 dark:text-slate-400">Tagihan Prorate</span>
                            <span id="qp-prorate" class="font-mono text-slate-800 dark:text-slate-200">Rp 0</span>
                        </div>
                        <div class="flex justify-between gap-4 hidden" id="qp-extra-cable-row">
                            <span class="text-slate-500 dark:text-slate-400">Biaya Kabel Tambahan</span>
                            <span id="qp-extra-cable" class="font-mono text-slate-800 dark:text-slate-200">Rp 0</span>
                        </div>
                        <div class="flex justify-between gap-4 hidden" id="qp-extra-installation-row">
                            <span class="text-slate-500 dark:text-slate-400">Jasa Instalasi Tambahan</span>
                            <span id="qp-extra-installation" class="font-mono text-slate-800 dark:text-slate-200">Rp 0</span>
                        </div>
                        <div class="flex justify-between gap-4 hidden" id="qp-extra-pole-row">
                            <span class="text-slate-500 dark:text-slate-400">Tambahan Tiang</span>
                            <span id="qp-extra-pole" class="font-mono text-slate-800 dark:text-slate-200">Rp 0</span>
                        </div>
                        <div class="flex justify-between gap-4 hidden" id="qp-other-row">
                            <span class="text-slate-500 dark:text-slate-400">Biaya Lain-lain</span>
                            <span id="qp-other" class="font-mono text-slate-800 dark:text-slate-200">Rp 0</span>
                        </div>
                        
                        <div class="border-t border-dashed border-slate-200 dark:border-slate-800 pt-2 space-y-1.5">
                            <div class="flex justify-between gap-4">
                                <span class="text-slate-900 dark:text-slate-100 font-bold uppercase text-[10px] tracking-wider">Total</span>
                                <span id="qp-total" class="font-mono font-bold text-slate-900 dark:text-slate-100">Rp 0</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-slate-500 dark:text-slate-400">Terbayar</span>
                                <span id="qp-paid" class="font-mono text-slate-600 dark:text-slate-300">Rp 0</span>
                            </div>
                            <div class="flex justify-between gap-4 pt-1 border-t border-slate-100 dark:border-slate-800">
                                <span class="text-slate-900 dark:text-slate-100 font-bold uppercase text-[10px] tracking-wider">Sisa Tagihan</span>
                                <span id="qp-due" class="font-mono font-bold text-emerald-600 dark:text-emerald-400">Rp 0</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Form Pembayaran / Pencatatan Tagihan -->
            <div class="flex flex-col justify-between h-full bg-white dark:bg-slate-900">
                <div class="p-6 space-y-4">
                    <h4 class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Form Pembayaran</h4>

                    <div id="qp-error" class="hidden text-xs text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-950/80 border border-red-200 dark:border-red-800 rounded-md px-3 py-2"></div>

                    <div>
                        <label for="qp-payment-date" class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Tanggal Bayar</label>
                        <input type="date" id="qp-payment-date" required
                               class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md shadow-sm focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25 text-xs font-mono text-slate-900 dark:text-slate-100 bg-white dark:bg-slate-800">
                    </div>

                    <div>
                        <label for="qp-payment-method" class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Metode Bayar</label>
                        <select id="qp-payment-method" required
                                class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md shadow-sm focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25 text-xs font-semibold text-slate-900 dark:text-slate-100 bg-white dark:bg-slate-800">
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer</option>
                            <option value="qris">QRIS</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label for="qp-amount" class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Nominal Diterima dari Pelanggan</label>
                        {{-- data-rupiah butuh type="text" (input number menolak
                             titik ribuan). `min` HTML ikut hilang bersamanya,
                             jadi batas bawah dijaga qpNominalValid() + validasi
                             server `amount|numeric|min:1`. --}}
                        <input type="text" inputmode="decimal" data-rupiah id="qp-amount" required
                               class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md shadow-sm focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25 text-xs font-mono text-slate-900 dark:text-slate-100 bg-white dark:bg-slate-800">
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">
                            Boleh diisi lebih besar dari sisa tagihan — kelebihannya otomatis tercatat sebagai lebih bayar.
                        </p>
                        {{-- Konsekuensi cicil/lunas/lebih bayar ditampilkan SEBELUM
                             submit — sinkron dengan hint di payments/create.blade.php,
                             cuma versi JS murni karena modal ini tak render Blade
                             ulang per invoice. --}}
                        <p id="qp-installment-hint" class="hidden text-[10px] font-semibold text-amber-700 dark:text-amber-400 mt-1.5 px-2 py-1.5 rounded bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20"></p>
                        <p id="qp-settle-hint" class="hidden text-[10px] font-semibold text-emerald-700 dark:text-emerald-400 mt-1.5 px-2 py-1.5 rounded bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20"></p>
                        <p id="qp-overpay-hint" class="hidden text-[10px] font-semibold text-sky-700 dark:text-sky-400 mt-1.5 px-2 py-1.5 rounded bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20"></p>
                    </div>

                    <div>
                        <label for="qp-allocation" class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Alokasi Pembayaran</label>
                        <select id="qp-allocation" required
                                class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md shadow-sm focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25 text-xs font-semibold text-slate-900 dark:text-slate-100 bg-white dark:bg-slate-800">
                            <option value="Tagihan Bulanan">Tagihan Bulanan</option>
                            <option value="Bayar Piutang">Bayar Piutang</option>
                            <option value="Lebih Bayar">Lebih Bayar</option>
                        </select>
                    </div>

                    <div>
                        <label for="qp-proof-file" class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Bukti Pembayaran</label>
                        <div class="relative flex items-center justify-center w-full border border-dashed border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600 rounded-md px-3 py-4 hover:bg-slate-50/80 dark:hover:bg-slate-800/60 transition-colors">
                            <input type="file" id="qp-proof-file" accept="image/png, image/jpeg, image/jpg, application/pdf"
                                   class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                            <div class="text-center">
                                <svg class="mx-auto h-6 w-6 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                </svg>
                                <span class="mt-1 block text-[10px] text-slate-500 dark:text-slate-400 font-medium" id="qp-file-name">Pilih atau Seret Foto/PDF (Maks. 2MB)</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label for="qp-note" class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1.5">Catatan</label>
                        <input type="text" id="qp-note" placeholder="Tulis catatan jika ada..."
                               class="w-full px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-md shadow-sm focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25 text-xs text-slate-900 dark:text-slate-100 bg-white dark:bg-slate-800">
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2 text-xs bg-slate-50/30 dark:bg-slate-800/40">
                    <button type="button" onclick="closeQuickPaymentModal()"
                            class="px-4 py-2 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 font-semibold rounded-md shadow-sm transition-colors cursor-pointer focus:outline-none">
                        Batal
                    </button>
                    <button type="submit" id="qp-submit-btn"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-md shadow-sm transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-emerald-500/25">
                        Catat Pembayaran
                    </button>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            let qpInvoiceId = null;
            // URL POST pencatatan pembayaran — SELALU dari server (data-payment-store-url
            // di tombol Bayar, route('invoices.payments.store')). Modal ini dipakai dari
            // dua halaman; merakit path sendiri berarti menduplikasi definisi route di
            // klien dan, kalau nilainya kosong, POST uang ke URL halaman. ADHOC-20 langkah 3.
            let qpPaymentStoreUrl = null;
            let qpRemainingAmount = 0;
            let qpNextInstallment = 1;

            function qpFormatRupiah(value) {
                return 'Rp ' + Math.round(value).toLocaleString('id-ID');
            }

            /** Nilai nominal sebagai angka, apa pun bentuk maskingnya. */
            function qpNominal() {
                const raw = document.getElementById('qp-amount').value;

                return window.Rupiah ? window.Rupiah.angka(raw) : parseFloat(raw);
            }

            /** Nilai untuk dikirim ke server: tanpa titik ribuan. */
            function qpNominalPolos() {
                const raw = document.getElementById('qp-amount').value;

                return window.Rupiah ? window.Rupiah.polos(raw) : raw;
            }

            /** Isi ulang field dengan format ribuan. */
            function qpSetNominal(nilai) {
                // Sisa tagihan diisi APA ADANYA, tanpa dibulatkan: membulatkan
                // sisa ber-sen berarti admin menyetujui nominal yang bukan sisa
                // sebenarnya, dan bagian pecahannya diam-diam jadi lebih bayar.
                const teks = String(nilai ?? '');
                document.getElementById('qp-amount').value = window.Rupiah ? window.Rupiah.formatDariServer(teks) : teks;
            }

            function qpRefreshInstallmentHint() {
                const installmentHint = document.getElementById('qp-installment-hint');
                const settleHint = document.getElementById('qp-settle-hint');
                const overpayHint = document.getElementById('qp-overpay-hint');
                // Input bermasking ribuan: parseFloat('150.000') = 150, dan
                // pratinjau cicilan/lebih bayar akan berbohong tanpa parser ini.
                const amount = qpNominal();

                installmentHint.classList.add('hidden');
                settleHint.classList.add('hidden');
                overpayHint.classList.add('hidden');

                if (isNaN(amount) || amount <= 0) {
                    return;
                }

                // qp-amount SENGAJA tak dibatasi `max` HTML — nominal boleh
                // melebihi sisa tagihan, kelebihannya otomatis jadi lebih
                // bayar (dihitung PaymentController::store(), bukan di sini;
                // ini cuma pratinjau sebelum submit).
                if (amount > qpRemainingAmount) {
                    const overpay = Math.round((amount - qpRemainingAmount) * 100) / 100;
                    overpayHint.textContent =
                        qpFormatRupiah(qpRemainingAmount) + ' diterapkan ke tagihan (Lunas), ' +
                        qpFormatRupiah(overpay) + ' tercatat sebagai lebih bayar.';
                    overpayHint.classList.remove('hidden');
                    return;
                }

                const leftover = Math.round((qpRemainingAmount - amount) * 100) / 100;

                if (leftover > 0) {
                    installmentHint.textContent =
                        'Tercatat sebagai Cicilan Ke-' + qpNextInstallment +
                        '. Tagihan jadi berstatus Sebagian, sisa setelah ini: ' + qpFormatRupiah(leftover) + '.';
                    installmentHint.classList.remove('hidden');
                } else {
                    settleHint.textContent = 'Pembayaran ini melunasi tagihan. Status jadi Lunas.';
                    settleHint.classList.remove('hidden');
                }
            }

            function openQuickPaymentModal(invoiceId, invoiceNumber, remainingAmount, paymentStoreUrl) {
                qpInvoiceId = invoiceId;
                qpPaymentStoreUrl = paymentStoreUrl || null;
                
                // Show modal and reset loading/error state
                document.getElementById('quick-payment-modal').classList.remove('hidden');
                document.getElementById('qp-error').classList.add('hidden');
                document.getElementById('qp-submit-btn').disabled = true;
                
                // Show skeleton or placeholder values
                document.getElementById('qp-pelanggan').textContent = 'Memuat...';
                document.getElementById('qp-alamat').textContent = 'Memuat...';
                document.getElementById('qp-paket').textContent = 'Memuat...';
                document.getElementById('qp-pop').textContent = 'Memuat...';
                document.getElementById('qp-invoice-number').textContent = invoiceNumber;
                document.getElementById('qp-no-tagihan-details').textContent = invoiceNumber;
                document.getElementById('qp-periode').textContent = 'Memuat...';
                document.getElementById('qp-jatuh-tempo').textContent = 'Memuat...';
                
                // Reset pricing views
                document.getElementById('qp-subtotal').textContent = 'Rp 0';
                document.getElementById('qp-discount-row').classList.add('hidden');
                document.getElementById('qp-ppn').textContent = 'Rp 0';
                document.getElementById('qp-prorate-row').classList.add('hidden');
                document.getElementById('qp-extra-cable-row').classList.add('hidden');
                document.getElementById('qp-extra-installation-row').classList.add('hidden');
                document.getElementById('qp-extra-pole-row').classList.add('hidden');
                document.getElementById('qp-other-row').classList.add('hidden');
                document.getElementById('qp-total').textContent = 'Rp 0';
                document.getElementById('qp-paid').textContent = 'Rp 0';
                document.getElementById('qp-due').textContent = 'Rp ' + Math.round(remainingAmount).toLocaleString('id-ID');
                
                // Reset form fields
                qpSetNominal(remainingAmount);
                document.getElementById('qp-payment-date').value = new Date().toISOString().slice(0, 10);
                document.getElementById('qp-payment-method').value = 'cash';
                document.getElementById('qp-allocation').value = 'Tagihan Bulanan';
                document.getElementById('qp-note').value = '';
                document.getElementById('qp-proof-file').value = '';
                document.getElementById('qp-file-name').textContent = 'Pilih atau Seret Foto/PDF (Maks. 2MB)';
                document.getElementById('qp-installment-hint').classList.add('hidden');
                document.getElementById('qp-settle-hint').classList.add('hidden');
                document.getElementById('qp-overpay-hint').classList.add('hidden');
                qpRemainingAmount = remainingAmount;
                qpNextInstallment = 1;

                // Fetch dynamic data via AJAX
                fetch(`/invoices/${invoiceId}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(async (res) => {
                    if (!res.ok) throw new Error('Gagal memuat detail tagihan.');
                    return res.json();
                })
                .then(data => {
                    // Populate data
                    document.getElementById('qp-pelanggan').textContent = data.customer ? data.customer.full_name : '-';
                    document.getElementById('qp-alamat').textContent = data.customer ? (data.customer.clean_address || '-') : '-';
                    
                    let packageName = '-';
                    if (data.internet_package) {
                        packageName = data.internet_package.name + ' - ' + data.internet_package.name;
                    } else if (data.customer && data.customer.internet_package) {
                        packageName = data.customer.internet_package.name + ' - ' + data.customer.internet_package.name;
                    }
                    document.getElementById('qp-paket').textContent = packageName;
                    
                    document.getElementById('qp-pop').textContent = data.pop ? data.pop.name : '-';
                    document.getElementById('qp-invoice-number').textContent = data.invoice_number;
                    document.getElementById('qp-no-tagihan-details').textContent = data.invoice_number;
                    document.getElementById('qp-periode').textContent = data.billing_period || '-';
                    
                    if (data.due_date) {
                        const d = new Date(data.due_date);
                        const formattedDate = ('0' + d.getDate()).slice(-2) + '/' + ('0' + (d.getMonth() + 1)).slice(-2) + '/' + d.getFullYear();
                        document.getElementById('qp-jatuh-tempo').textContent = formattedDate;
                    } else {
                        document.getElementById('qp-jatuh-tempo').textContent = '-';
                    }

                    // Render costs
                    const subtotal = parseFloat(data.subtotal) || 0;
                    const discount = parseFloat(data.discount) || 0;
                    const ppnPercent = parseFloat(data.ppn) || 0;
                    const prorate = parseFloat(data.prorate_amount) || 0;
                    const extraCable = parseFloat(data.extra_cable_fee) || 0;
                    const extraInstallation = parseFloat(data.extra_installation_fee) || 0;
                    const extraPole = parseFloat(data.extra_pole_fee) || 0;
                    const other = parseFloat(data.other_fee) || 0;
                    
                    const afterDiscount = Math.max(0, subtotal - discount);
                    const ppnAmount = Math.round(afterDiscount * (ppnPercent / 100));
                    
                    document.getElementById('qp-subtotal').textContent = 'Rp ' + Math.round(subtotal).toLocaleString('id-ID');
                    if (discount > 0) {
                        document.getElementById('qp-discount-row').classList.remove('hidden');
                        document.getElementById('qp-discount').textContent = '- Rp ' + Math.round(discount).toLocaleString('id-ID');
                    }
                    
                    document.getElementById('qp-ppn-label').textContent = 'PPN (' + Math.round(ppnPercent) + '%)';
                    document.getElementById('qp-ppn').textContent = 'Rp ' + Math.round(ppnAmount).toLocaleString('id-ID');
                    
                    if (prorate > 0) {
                        document.getElementById('qp-prorate-row').classList.remove('hidden');
                        document.getElementById('qp-prorate').textContent = 'Rp ' + Math.round(prorate).toLocaleString('id-ID');
                    }
                    if (extraCable > 0) {
                        document.getElementById('qp-extra-cable-row').classList.remove('hidden');
                        document.getElementById('qp-extra-cable').textContent = 'Rp ' + Math.round(extraCable).toLocaleString('id-ID');
                    }
                    if (extraInstallation > 0) {
                        document.getElementById('qp-extra-installation-row').classList.remove('hidden');
                        document.getElementById('qp-extra-installation').textContent = 'Rp ' + Math.round(extraInstallation).toLocaleString('id-ID');
                    }
                    if (extraPole > 0) {
                        document.getElementById('qp-extra-pole-row').classList.remove('hidden');
                        document.getElementById('qp-extra-pole').textContent = 'Rp ' + Math.round(extraPole).toLocaleString('id-ID');
                    }
                    if (other > 0) {
                        document.getElementById('qp-other-row').classList.remove('hidden');
                        document.getElementById('qp-other').textContent = 'Rp ' + Math.round(other).toLocaleString('id-ID');
                    }

                    const totalAmount = parseFloat(data.total_amount) || 0;
                    const paidAmount = parseFloat(data.paid_amount) || 0;
                    const remainingAmountFromDb = parseFloat(data.remaining_amount) || 0;

                    document.getElementById('qp-total').textContent = 'Rp ' + Math.round(totalAmount).toLocaleString('id-ID');
                    document.getElementById('qp-paid').textContent = 'Rp ' + Math.round(paidAmount).toLocaleString('id-ID');
                    document.getElementById('qp-due').textContent = 'Rp ' + Math.round(remainingAmountFromDb).toLocaleString('id-ID');

                    qpSetNominal(remainingAmountFromDb);

                    qpRemainingAmount = remainingAmountFromDb;
                    // Sama seperti PaymentController::create() — cuma hitung
                    // pembayaran VALID biar nomor cicilan tak digeser payment
                    // yang ditolak.
                    const validPayments = Array.isArray(data.payments)
                        ? data.payments.filter(p => p.payment_status === 'valid')
                        : [];
                    qpNextInstallment = validPayments.length + 1;
                    qpRefreshInstallmentHint();

                    document.getElementById('qp-submit-btn').disabled = false;
                })
                .catch(err => {
                    document.getElementById('qp-error').textContent = err.message || 'Gagal memuat detail tagihan.';
                    document.getElementById('qp-error').classList.remove('hidden');
                    document.getElementById('qp-submit-btn').disabled = false;
                });
            }

            function closeQuickPaymentModal() {
                document.getElementById('quick-payment-modal').classList.add('hidden');
                qpInvoiceId = null;
                qpPaymentStoreUrl = null;
            }

            document.getElementById('qp-amount')?.addEventListener('input', qpRefreshInstallmentHint);

            // Sync file upload input text label
            document.getElementById('qp-proof-file')?.addEventListener('change', function(e) {
                const fileName = e.target.files[0]?.name || 'Pilih atau Seret Foto/PDF (Maks. 2MB)';
                document.getElementById('qp-file-name').textContent = fileName;
            });

            // Form Submit Handler
            document.getElementById('quick-payment-form')?.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!qpInvoiceId || !qpPaymentStoreUrl) return;

                const submitBtn = document.getElementById('qp-submit-btn');
                const errorBox = document.getElementById('qp-error');
                errorBox.classList.add('hidden');

                // Batas bawah dulu ditegakkan atribut `min` milik input number.
                // Sejak kolomnya jadi teks bermasking, cek itu harus di sini —
                // kalau tidak, nominal 0 baru ketahuan salah setelah bolak-balik
                // ke server.
                const nominal = qpNominal();
                if (isNaN(nominal) || nominal < 1) {
                    errorBox.textContent = 'Nominal wajib diisi minimal Rp 1.';
                    errorBox.classList.remove('hidden');
                    document.getElementById('qp-amount').focus();
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.textContent = 'Menyimpan...';

                // Construct payload
                const payload = new FormData();
                // amount = TOTAL diterima dari pelanggan. Server yang
                // memisah bagian penutup tagihan vs lebih bayar — lihat
                // PaymentController::store().
                // Dikirim tanpa titik ribuan. Modal ini submit lewat FormData,
                // bukan event `submit`, jadi normalisasi global di
                // layouts/app.blade.php tidak ikut jalan di sini.
                payload.append('amount', qpNominalPolos());
                payload.append('payment_date', document.getElementById('qp-payment-date').value);
                payload.append('payment_method', document.getElementById('qp-payment-method').value);

                // Format note: "[Alokasi Pembayaran] - [Catatan]"
                const allocation = document.getElementById('qp-allocation').value;
                const userNote = document.getElementById('qp-note').value.trim();
                const note = userNote ? allocation + ' - ' + userNote : allocation;
                payload.append('note', note);

                // Add proof file if present
                const fileInput = document.getElementById('qp-proof-file');
                if (fileInput.files.length > 0) {
                    payload.append('proof_file', fileInput.files[0]);
                }

                fetch(qpPaymentStoreUrl, {
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
                    closeQuickPaymentModal();

                    // Broadcast lokal: halaman yang tahu cara update barisnya
                    // sendiri (invoices/index, collectors/show) dengar event
                    // ini dan patch DOM tanpa reload. Payload sama bentuknya
                    // dengan App\Events\InvoiceStatusUpdated biar satu handler
                    // dipakai buat update lokal (punya sendiri) maupun remote
                    // (Echo, punya user lain di POP yang sama).
                    const invoiceUpdate = data.invoice ? {
                        invoice_id: data.invoice.id,
                        invoice_status: data.invoice.invoice_status,
                        paid_amount: data.invoice.paid_amount,
                        remaining_amount: data.invoice.remaining_amount,
                    } : null;

                    // dispatchEvent() balikin false kalau ada listener yang
                    // preventDefault() — di sini itu sinyal "sudah kupatch
                    // sendiri, jangan reload".
                    const handled = invoiceUpdate
                        ? !document.dispatchEvent(new CustomEvent('payment-recorded', { detail: invoiceUpdate, cancelable: true }))
                        : false;

                    window.Dialog.show({
                        title: 'Pembayaran Tercatat',
                        message: data.message,
                        icon: 'success',
                        buttons: [{
                            text: 'Tutup', type: 'primary', onClick: () => {
                                window.Dialog.close();
                                // Listener yang berhasil update DOM-nya sendiri
                                // memanggil preventDefault() — reload cuma jadi
                                // fallback buat halaman yang belum tahu cara
                                // patch barisnya (mis. tab Tagihan pelanggan).
                                if (!handled) {
                                    window.location.reload();
                                }
                            },
                        }],
                    });
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
